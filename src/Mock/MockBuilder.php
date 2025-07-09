<?php

namespace MicroUnit\Mock;

class MockBuilder
{
    private MicroMock $engine;

    private function __construct(string $targetType)
    {
        $this->engine = new MicroMock($targetType);
    }

    public static function create(string $class): self
    {
        return new self($class, 'concrete');
    }

    public function returns(string $method, mixed $value): self
    {
        $this->setReturnPlan($method, ReturnPlanType::FIXED, $value);
        return $this;
    }

    public function returnsSequence(string $method, mixed ...$values): self
    {
        $this->setReturnPlan($method, ReturnPlanType::FIXED, $values);
        return $this;
    }

    public function returnsCallback(string $method, callable $callback): self
    {
        $this->setReturnPlan($method, ReturnPlanType::CALLBACK, $callback);
        return $this;
    }
    public function throws(string $method, \Throwable $e): self
    {
        $this->setReturnPlan($method, ReturnPlanType::THROWABLE, $e);
        return $this;
    }

    public function keepOriginalMethodBehaviour(string $method): self
    {
        $this->engine->keepOriginalBehavior[$method] = true;
        return $this;
    }

    public function disableOriginalConstructor(): self
    {
        $this->engine->callOrginalConstructor = false;
        return $this;
    }

    /** Set a method that will be executed inside the constructor.
     * @param callable(object $mockInstance, array $constructorArgs): void $fn
     */
    public function executeInConstructor(callable $fn): self
    {
        $this->engine->constructorCallable = $fn;
        return $this;
    }

    /** @param array<mixed> $args */
    public function withConstructorArgs(array $args): self
    {
        $this->engine->constructorArgs = $args;
        return $this;
    }

    public function build(): MicroMock
    {
        return $this->engine;
    }

    private function setReturnPlan(string $method, ReturnPlanType $returnType, mixed $return): void
    {
        $this->engine->returnPlans[$method] = new ReturnPlan($returnType, $return);
    }
}
