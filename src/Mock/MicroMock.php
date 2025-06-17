<?php

namespace MicroUnit\Mock;

class MicroMock
{
    private string $targetType;
    private array $returnPlans = [];
    private CallLog $callLog;

    public function __construct(string $targetType)
    {
        $this->targetType = $targetType;
        $this->callLog = new CallLog();
    }

    public function setReturnPlan(string $method, ReturnPlanType $returnType, mixed $return): void
    {
        $this->returnPlans[$method] = new ReturnPlan($returnType, $return);
    }

    public function getCallLog(): CallLog
    {
        return $this->callLog;
    }

    public function handleCall(string $method, array $args): mixed
    {
        $this->callLog->addCall($method, $args);

        if (isset($this->returnPlans[$method])) {
            return $this->returnPlans[$method]->execute($args);
        }

        return null;
    }

    public function newInstance(): object
    {
        $target = $this->targetType;
        $ref = new \ReflectionClass($target);
        $engine = $this;

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

                $overrideMethods .= "
                public function {$name}(" . implode(', ', $params) . "){$returnType} {
                    return \$this->engine->handleCall('{$name}', func_get_args());
                }
                ";
            }
        }

        $code = "
        namespace MicroUnit\\Mock;

        class {$className} {$declaration} {
            private \\MicroUnit\\Mock\\MicroMock \$engine;

            public function __construct(\\MicroUnit\\Mock\\MicroMock \$engine) {
                \$this->engine = \$engine;
            }

            public function __call(\$name, \$args) {
                return \$this->engine->handleCall(\$name, \$args);
            }

            {$overrideMethods}
        }
        ";

        eval($code);

        $fqcn = "MicroUnit\\Mock\\{$className}";
        return new $fqcn($engine);
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
}
