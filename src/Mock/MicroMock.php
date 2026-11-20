<?php

namespace MicroUnit\Mock;

/**
 * A built mock. Creates instances of the mocked type and keeps two kinds of call logs: one
 * aggregated over every instance it created and one per instance.
 *
 * Return values can still change after build(), e.g. when a fixture creates the mock and each test
 * decides what it returns:
 *
 *     MicroMock::of($mailer)->returns('send', false);
 */
final class MicroMock
{
    private readonly GeneratedMock $generated;

    private readonly CallLog $callLog;

    /** @var \WeakMap<object, CallLog> */
    private readonly \WeakMap $instanceCallLogs;

    /** @var array<string, int> method => number of times its return plan was resolved */
    private array $resolvedCallCounts = [];

    public function __construct(
        private MockDefinition $definition,
    ) {
        $this->generated = MockClassGenerator::generate($definition);
        $this->callLog = new CallLog();
        $this->instanceCallLogs = new \WeakMap();
    }

    /** The mock that created $instance. */
    public static function of(object $instance): self
    {
        return MockRegistry::ownerOf($instance) ?? throw new MockException(
            'This ' . $instance::class . ' is not a MicroUnit mock instance (create it with MockBuilder ... ->build()->newInstance()).',
        );
    }

    /** From now on $method always returns $value. */
    public function returns(string $method, mixed $value): self
    {
        return $this->plan($method, ReturnPlan::fixed($value));
    }

    /** From now on each call returns the next value; once exhausted the method returns its default. */
    public function returnsSequence(string $method, mixed ...$values): self
    {
        return $this->plan($method, ReturnPlan::sequence(array_values($values)));
    }

    /** From now on the callback receives the call's arguments; its return value is returned. */
    public function returnsCallback(string $method, callable $callback): self
    {
        return $this->plan($method, ReturnPlan::callback($callback));
    }

    /** @param list<list<mixed>> $map see MockBuilder::returnsMap() */
    public function returnsMap(string $method, array $map): self
    {
        return $this->plan($method, ReturnPlan::map($map));
    }

    public function throws(string $method, \Throwable $throwable): self
    {
        return $this->plan($method, ReturnPlan::throwing($throwable));
    }

    /** Creates an instance using the constructor arguments configured on the builder. */
    public function newInstance(): object
    {
        return $this->instantiate($this->definition->constructorArgs);
    }

    /** @param list<mixed> $constructorArgs used instead of the ones configured on the builder */
    public function newAlternateInstance(array $constructorArgs): object
    {
        return $this->instantiate(array_values($constructorArgs));
    }

    /** Calls made on every instance this mock created. */
    public function getCallLog(): CallLog
    {
        return $this->callLog;
    }

    /** Calls made on one specific instance. */
    public function getInstanceCallLog(object $instance): CallLog
    {
        return $this->instanceCallLogs[$instance] ?? throw new \InvalidArgumentException(
            'The given object was not created by this mock.',
        );
    }

    /** @return class-string */
    public function getTargetType(): string
    {
        return $this->definition->targetType;
    }

    /**
     * @internal called by generated mock classes through MockRegistry
     * @param list<mixed> $args
     */
    public function invoke(object $instance, string $method, array $args): mixed
    {
        $this->record($instance, $method, $args);

        return $this->resolve($instance, $method, $args);
    }

    /**
     * @internal
     * @param list<mixed> $args
     */
    public function record(object $instance, string $method, array $args): void
    {
        $this->callLog->addCall($method, $args);
        $this->instanceCallLogs[$instance]->addCall($method, $args);
    }

    /**
     * @internal
     * @param list<mixed> $args
     */
    public function resolve(object $instance, string $method, array $args): mixed
    {
        $plan = $this->definition->returnPlans[$method] ?? null;
        $callIndex = $this->resolvedCallCounts[$method] ?? 0;
        $this->resolvedCallCounts[$method] = $callIndex + 1;

        $planned = $plan?->lookup($args, $callIndex);
        if ($planned !== null) {
            return $planned[0];
        }
        $default = $this->generated->defaultReturns[$method] ?? null;

        return $default === null ? null : $default($instance);
    }

    private function plan(string $method, ReturnPlan $plan): self
    {
        $method = $this->definition->declaredName($method);
        $isIntercepted = isset($this->generated->defaultReturns[$method]) || method_exists($this->generated->className, '__call') && !method_exists($this->definition->targetType, $method);
        if (!$isIntercepted) {
            throw new MockException("Cannot configure {$this->definition->targetType}::$method() after build(): it is not intercepted by this mock (it keeps its real behaviour, or it does not exist).");
        }
        $this->definition = $this->definition->withPlan($method, $plan);
        unset($this->resolvedCallCounts[$method]);

        return $this;
    }

    /** @param list<mixed> $constructorArgs */
    private function instantiate(array $constructorArgs): object
    {
        $class = new \ReflectionClass($this->generated->className);
        $instance = $class->newInstanceWithoutConstructor();

        // Register first: the original constructor may already call mocked methods.
        $this->instanceCallLogs[$instance] = new CallLog();
        MockRegistry::register($instance, $this);

        $constructor = $class->getConstructor();
        if ($this->definition->callOriginalConstructor && $constructor !== null && !$constructor->isAbstract()) {
            $constructor->invokeArgs($instance, $constructorArgs);
        }

        if ($this->definition->constructorCallback !== null) {
            ($this->definition->constructorCallback)($instance, $constructorArgs);
        }

        return $instance;
    }
}
