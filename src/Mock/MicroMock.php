<?php

namespace MicroUnit\Mock;

use RuntimeException;

class MicroMock
{
    public array $returnPlans = [];
    public bool $callOrginalConstructor = true;
    /** @var callable(object $mockInstance, array $constructorArgs): void */
    public $constructorCallable;
    /** @var array<mixed> */
    public array $constructorArgs = [];
    /** @var array<string, bool> Map method name => keep original behavior? */
    public array $keepOriginalBehavior = [];

    private string $targetType;
    private CallLog $callLog;

    public function __construct(string $targetType)
    {
        $this->targetType = $targetType;
        $this->callLog = new CallLog();
    }

    public function getCallLog(): CallLog
    {
        return $this->callLog;
    }

    public function newAlternateInstance(array $constructorArgs)
    {
        return $this->new($constructorArgs);
    }

    public function newInstance(): object
    {
        return $this->new();
    }

    private function new(?array $alternateCtorArgs = null): object
    {
        $constructorArgs = $alternateCtorArgs ?? $this->constructorArgs;

        $target = $this->targetType;
        $ref = new \ReflectionClass($target);
        $parentConstructor = $ref->getConstructor();

        $className = 'Mock_' . str_replace('\\', '_', $target) . '_' . uniqid();
        $declaration = '';
        $overrideMethods = '';

        if ($ref->isInterface()) {
            $declaration = "implements \\{$target}";
        } else {
            $declaration = "extends \\{$target}";

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isFinal() || $method->isStatic()) {
                    continue;
                }

                $name = $method->getName();

                // Skip constructor (handle separately)
                if ($name === '__construct') {
                    continue;
                }

                $params = [];
                foreach ($method->getParameters() as $param) {
                    $paramDecl = '';

                    if ($type = $param->getType()) {
                        $paramDecl .= $this->stringifyType($type) . ' ';
                    }

                    if ($param->isPassedByReference()) {
                        $paramDecl .= '&';
                    }

                    if ($param->isVariadic()) {
                        $paramDecl .= '...';
                    }

                    $paramDecl .= '$' . $param->getName();

                    if ($param->isDefaultValueAvailable() && !$param->isVariadic()) {
                        $paramDecl .= ' = ' . var_export($param->getDefaultValue(), true);
                    }

                    $params[] = $paramDecl;
                }

                $returnType = '';
                if ($type = $method->getReturnType()) {
                    $returnType = ': ' . $this->stringifyType($type);
                }
                $keepOriginal = $this->keepOriginalBehavior[$name] ?? false;

                $overrideMethods .= "
        public function {$name}(" . implode(', ', $params) . "){$returnType} {
            \$callMethodReturn = (\$this->handleCallMethod)('{$name}', func_get_args());

        ";
                if ($keepOriginal) {
                    $isAbstract = $method->isAbstract();
                    $isDeclaredInInterface = $method->getDeclaringClass()->isInterface();

                    if ($isAbstract || $isDeclaredInInterface) {
                        throw new RuntimeException("Cannot keep original behaviour of static or interface method '$name'.");
                    }

                    if (isset($this->returnPlans[$name])) {
                        $overrideMethods .= "parent::{$name}(...func_get_args());
                                               return \$callMethodReturn;" . PHP_EOL;
                    } else {
                        $overrideMethods .= "return parent::{$name}(...func_get_args());" . PHP_EOL;
                    }
                } else {
                    $overrideMethods .= 'return $callMethodReturn;' . PHP_EOL;
                }

                $overrideMethods .= '}';
            }
        }

        $code = "
namespace MicroUnit\\Mock;

class {$className} {$declaration} {
    private \$handleCallMethod;

    public function __construct(
        callable \$handleCallMethod,
        bool \$callOriginalConstructor,
        array \$constructorArgs,
        ?callable \$constructorCallback = null
    ) {
        \$this->handleCallMethod = \$handleCallMethod;

        if (\$callOriginalConstructor) {
            parent::__construct(...\$constructorArgs);
        }

        if (\$constructorCallback) {
            (\$constructorCallback)(\$this, \$constructorArgs);
        }
    }

    public function __call(\$name, \$args) {
        return (\$this->handleCallMethod)(\$name, \$args);
    }

    {$overrideMethods}
}
";

        eval($code);

        $fqcn = "MicroUnit\\Mock\\{$className}";
        return new $fqcn(
            fn(string $method, array $args) => $this->handleCall($method, $args),
            $this->callOrginalConstructor && $parentConstructor !== null && $parentConstructor->isPublic(),
            $constructorArgs,
            $this->constructorCallable
        );
    }

    private function stringifyType(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();
            $prefix = $type->allowsNull() && $name !== 'mixed' ? '?' : '';
            if (!$type->isBuiltin()) {
                $name = '\\' . ltrim($name, '\\');
            }
            return $prefix . $name;
        }

        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map([$this, 'stringifyType'], $type->getTypes()));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map([$this, 'stringifyType'], $type->getTypes()));
        }

        return '';
    }

    private function handleCall(string $method, array $args): mixed
    {
        $this->callLog->addCall($method, $args);

        if (isset($this->returnPlans[$method])) {
            return $this->returnPlans[$method]->execute($args);
        }

        return null;
    }
}
