<?php

namespace MicroUnit\Mock;

/**
 * Fluent entry point for creating mocks:
 *
 *     $mock = MockBuilder::create(Mailer::class)->returns('send', true)->build();
 *     $mailer = $mock->newInstance();
 */
final class MockBuilder
{
    private MockDefinition $definition;

    /** @param class-string $targetType */
    private function __construct(string $targetType)
    {
        $this->definition = new MockDefinition($targetType);
    }

    /** @param class-string $targetType class, abstract class or interface to mock */
    public static function create(string $targetType): self
    {
        return new self($targetType);
    }

    /** The method always returns $value. */
    public function returns(string $method, mixed $value): self
    {
        $this->definition = $this->definition->withPlan($method, ReturnPlan::fixed($value));
        return $this;
    }

    /** Each call returns the next value; once exhausted the method returns its default (null, 0, '', [] ... by return type). */
    public function returnsSequence(string $method, mixed ...$values): self
    {
        $this->definition = $this->definition->withPlan($method, ReturnPlan::sequence(array_values($values)));
        return $this;
    }

    /** The callback receives the call's arguments; its return value is returned. */
    public function returnsCallback(string $method, callable $callback): self
    {
        $this->definition = $this->definition->withPlan($method, ReturnPlan::callback($callback));
        return $this;
    }

    /**
     * Returns the value of the first row whose leading values equal the call's arguments (===);
     * the last value of each row is the return value:
     *
     *     ->returnsMap('price', [['EUR', 10], ['USD', 11]])
     *
     * @param list<list<mixed>> $map
     */
    public function returnsMap(string $method, array $map): self
    {
        $this->definition = $this->definition->withPlan($method, ReturnPlan::map($map));

        return $this;
    }

    public function throws(string $method, \Throwable $throwable): self
    {
        $this->definition = $this->definition->withPlan($method, ReturnPlan::throwing($throwable));
        return $this;
    }

    /**
     * Runs the real implementation of $method. Calls are still recorded; if a return plan is
     * configured as well, the real method runs first and the planned value is returned.
     */
    public function keepOriginalMethodBehaviour(string $method): self
    {
        $this->definition = $this->definition->withOriginalBehaviour($method);
        return $this;
    }

    /**
     * A partial mock: only these methods are intercepted, every other method runs its real
     * implementation (calls are still recorded).
     */
    public function interceptOnly(string ...$methods): self
    {
        $this->definition = $this->definition->withInterceptedOnly(array_values($methods));

        return $this;
    }

    public function disableOriginalConstructor(): self
    {
        $this->definition = $this->definition->withoutOriginalConstructor();
        return $this;
    }

    /**
     * Runs $callback whenever an instance is created — after the original constructor, if that is called.
     *
     * @param callable(object $instance, list<mixed> $constructorArgs): void $callback
     */
    public function executeInConstructor(callable $callback): self
    {
        $this->definition = $this->definition->withConstructorCallback($callback(...));
        return $this;
    }

    /** @param list<mixed> $args arguments passed to the original constructor by newInstance() */
    public function withConstructorArgs(array $args): self
    {
        $this->definition = $this->definition->withConstructorArgs($args);
        return $this;
    }

    #[\NoDiscard('build() returns the mock; without it nothing can be instantiated or asserted')]
    public function build(): MicroMock
    {
        return new MicroMock($this->definition);
    }
}
