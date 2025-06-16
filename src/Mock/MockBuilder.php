<?php

namespace MicroUnit\Mock;

class MockBuilder
{
    private MicroMock $engine;

    private function __construct(string $targetType)
    {
        $this->engine = new MicroMock($targetType);
    }

    public static function createMock(string $class): self
    {
        return new self($class, 'concrete');
    }

    public function returns(string $method, mixed $value): self
    {
        $this->engine->setReturnPlan($method, ReturnPlanType::FIXED, $value);
        return $this;
    }

    public function returnsSequence(string $method, mixed ...$values): self
    {
        $this->engine->setReturnPlan($method, ReturnPlanType::FIXED, $values);
        return $this;
    }

    public function returnsCallback(string $method, callable $callback): self
    {
        $this->engine->setReturnPlan($method, ReturnPlanType::CALLBACK, $callback);
        return $this;
    }

    public function throws(string $method, \Throwable $e): self
    {
        $this->engine->setReturnPlan($method, ReturnPlanType::CALLBACK, $e);
        return $this;
    }

    public function shouldBeCalledTimes(string $method, int $times): self
    {
        $this->engine->setExpectation($method, ExpectationKind::TIMES, $times);
        return $this;
    }

    public function shouldBeCalledWith(string $method, array $args): self
    {
        $this->engine->setExpectation($method, ExpectationKind::ARGS, $args);
        return $this;
    }

    public function build(): object
    {
        return $this->engine->generateMock();
    }
}
