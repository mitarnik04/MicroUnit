<?php

namespace MicroUnit\Mock;

class MicroMock
{
    private string $targetType;
    private array $returnPlans = [];
    private array $callLog = [];
    private array $expectations = [];

    public function setReturnPlan(string $method, ReturnPlanType $returnType, mixed $return): void
    {
        $this->returnPlans[$method] = new ReturnPlan($returnType, $return);
    }

    public function setExpectation(string $method, ExpectationKind $kind, mixed $value): void
    {
        $this->expectations[$method][$kind->value] = $value;
    }

    public function __construct(string $targetType)
    {
        $this->targetType = $targetType;
    }

    public function handleCall(string $method, array $args): mixed
    {
        $this->callLog[] = ['method' => $method, 'args' => $args];

        if (isset($this->returnPlans[$method])) {
            return $this->returnPlans[$method]->execute($args);
        }

        return null;
    }

    public function verify(): void
    {
        foreach ($this->expectations as $method => $exp) {
            $calls = array_filter($this->callLog, fn($c) => $c['method'] === $method);

            if (isset($exp['times']) && count($calls) !== $exp['times']) {
                throw new \Exception("Expected {$method} to be called {$exp['times']} times, called " . count($calls));
            }

            if (isset($exp['args'])) {
                foreach ($exp['args'] as $expectedArgs) {
                    $matched = false;
                    foreach ($calls as $call) {
                        if ($call['args'] === $expectedArgs) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        throw new \Exception("Expected {$method} to be called with " . json_encode($expectedArgs));
                    }
                }
            }
        }
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

    public function generateMock(): object
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
}
