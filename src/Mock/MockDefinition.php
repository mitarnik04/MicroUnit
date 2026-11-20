<?php

namespace MicroUnit\Mock;

/** Everything a MockBuilder collected. Immutable: every change returns a modified copy. */
final readonly class MockDefinition
{
    /**
     * @param class-string $targetType
     * @param array<string, ReturnPlan> $returnPlans method => plan
     * @param array<string, true> $originalBehaviourMethods methods whose real implementation runs
     * @param list<mixed> $constructorArgs
     * @param (\Closure(object, list<mixed>): void)|null $constructorCallback
     * @param array<string, true>|null $interceptedMethods lowercase names; null = every method is intercepted
     */
    public function __construct(
        public string $targetType,
        public array $returnPlans = [],
        public array $originalBehaviourMethods = [],
        public bool $callOriginalConstructor = true,
        public array $constructorArgs = [],
        public ?\Closure $constructorCallback = null,
        public ?array $interceptedMethods = null,
    ) {}

    /**
     * Whether calls to $method run the real implementation: when asked for explicitly, or for every
     * method outside interceptOnly(). Abstract methods have no implementation and are always intercepted.
     */
    public function runsOriginal(string $method, bool $isAbstract): bool
    {
        if ($isAbstract) {
            return false;
        }

        return $this->keepsOriginalBehaviour($method)
            || ($this->interceptedMethods !== null && !isset($this->interceptedMethods[strtolower($method)]));
    }

    /** @param list<string> $methods */
    public function withInterceptedOnly(array $methods): self
    {
        return clone($this, ['interceptedMethods' => array_fill_keys(array_map('strtolower', $methods), true)]);
    }

    public function hasPlan(string $method): bool
    {
        return isset($this->returnPlans[$method]);
    }

    public function keepsOriginalBehaviour(string $method): bool
    {
        return isset($this->originalBehaviourMethods[$method]);
    }

    public function withPlan(string $method, ReturnPlan $plan): self
    {
        return clone($this, ['returnPlans' => [...$this->returnPlans, $this->declaredName($method) => $plan]]);
    }

    public function withOriginalBehaviour(string $method): self
    {
        return clone($this, ['originalBehaviourMethods' => [...$this->originalBehaviourMethods, $this->declaredName($method) => true]]);
    }

    /** "Send" => "send": PHP method names are case-insensitive, the plans are looked up by the declared name. */
    public function declaredName(string $method): string
    {
        return method_exists($this->targetType, $method) ? new \ReflectionMethod($this->targetType, $method)->name : $method;
    }

    public function withoutOriginalConstructor(): self
    {
        return clone($this, ['callOriginalConstructor' => false]);
    }

    /** @param list<mixed> $args */
    public function withConstructorArgs(array $args): self
    {
        return clone($this, ['constructorArgs' => array_values($args)]);
    }

    public function withConstructorCallback(\Closure $callback): self
    {
        return clone($this, ['constructorCallback' => $callback]);
    }
}
