<?php

namespace MicroUnit\Mock;

use ReflectionClass;
use ReflectionNamedType;

use MicroUnit\Mock\Representation\AccessModifier;
use MicroUnit\Mock\Representation\ClassRepresentation;
use MicroUnit\Mock\Representation\MethodRepresentation;
use MicroUnit\Mock\Representation\MethodParameterRepresentation;
use MicroUnit\Mock\Representation\TypeCombination;
use ReflectionIntersectionType;
use ReflectionParameter;
use ReflectionUnionType;

class MockFactory
{
    public static function create(string $type, array $stubs): Mock
    {
        if (!interface_exists($type) && !class_exists($type)) {
            throw new \InvalidArgumentException("Type $type does not exist.");
        }

        $mockId = uniqid('Mock_', true);
        $class = new ClassRepresentation(str_replace('.', '_', $mockId));

        $refClass = new ReflectionClass($type);
        $class->classMembers =  self::getMethodRepresentations($refClass, $stubs);


        if ($refClass->isInterface()) {
            $class->implementedClassName = $type;
        } else {
            $class->extendedClassName = $type;
        }

        eval($class->asString());
        $instance = new ($class->className)();

        return new Mock($instance);
    }

    private static function getMethodRepresentations(ReflectionClass $refClass, array $stubs): array
    {
        $methods = [];

        foreach ($stubs as $method => $returnValue) {
            if (!$refClass->hasMethod($method)) {
                throw new \RuntimeException("Method $method does not exist on {$refClass->getName()}");
            }

            $refMethod = $refClass->getMethod($method);
            $params = self::getParams($refMethod);

            $returnType = null;
            $returnTypeAllowsNull = false;
            $typeCombination = null;
            if ($refMethod->hasReturnType()) {
                $returnTypeObj = $refMethod->getReturnType();
                $returnTypeAllowsNull = $returnTypeObj->allowsNull();
                if ($returnTypeObj instanceof ReflectionNamedType) {
                    $returnType = $returnTypeObj->getName();
                } else {
                    $returnType = self::getTypeNames($returnTypeObj);
                    $typeCombination = $returnTypeObj instanceof ReflectionUnionType
                        ? TypeCombination::UNION
                        : TypeCombination::INTERSECTION;
                }
            }

            $return = var_export($returnValue, true);

            $methods[] = new MethodRepresentation(
                AccessModifier::PUBLIC,
                $method,
                $params,
                $returnType,
                $return,
                $returnTypeAllowsNull,
                $typeCombination
            );
        }

        return $methods;
    }

    private static function getParams(\ReflectionMethod $refMethod): array
    {
        return array_map(fn($param) => self::buildParam($param), $refMethod->getParameters());
    }

    private static function buildParam(ReflectionParameter $param): MethodParameterRepresentation
    {

        $paramRep = new MethodParameterRepresentation($param->getName());

        $paramType = $param->getType();
        if (is_null($paramType)) {
            $paramRep->type = null;
        } else if ($paramType instanceof ReflectionNamedType) {
            $paramRep->type = $paramType->getName();
        } else {
            $paramRep->type = self::getTypeNames($paramType);
            $paramRep->typeCombination = $paramType instanceof ReflectionUnionType
                ? TypeCombination::UNION
                : TypeCombination::INTERSECTION;
        }

        $paramRep->isPassedByReference = $param->isPassedByReference();
        $paramRep->isVariadic =  $param->isVariadic();

        if ($param->isDefaultValueAvailable() && !$param->isVariadic()) {
            $paramRep->default = var_export($param->getDefaultValue(), true);
        }

        return $paramRep;
    }

    private static function getTypeNames(ReflectionUnionType|ReflectionIntersectionType $refType)
    {
        return array_map(fn($t) => $t->getName(), $refType->getTypes());
    }
}
