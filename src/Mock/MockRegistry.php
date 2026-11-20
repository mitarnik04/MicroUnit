<?php

namespace MicroUnit\Mock;

/**
 * Maps mock instances to the MicroMock that created them. Generated mock methods call into this
 * registry, so generated classes need no injected state and stay independent of any one mock.
 *
 * @internal
 */
final class MockRegistry
{
    /** @var \WeakMap<object, MicroMock>|null */
    private static ?\WeakMap $owners = null;

    private function __construct() {}

    public static function register(object $instance, MicroMock $owner): void
    {
        self::$owners ??= new \WeakMap();
        self::$owners[$instance] = $owner;
    }

    public static function ownerOf(object $instance): ?MicroMock
    {
        return self::$owners[$instance] ?? null;
    }

    /** @param list<mixed> $args */
    public static function invoke(object $instance, string $method, array $args): mixed
    {
        return self::owner($instance)->invoke($instance, $method, $args);
    }

    /** @param list<mixed> $args */
    public static function record(object $instance, string $method, array $args): void
    {
        self::owner($instance)->record($instance, $method, $args);
    }

    /** @param list<mixed> $args */
    public static function resolve(object $instance, string $method, array $args): mixed
    {
        return self::owner($instance)->resolve($instance, $method, $args);
    }

    private static function owner(object $instance): MicroMock
    {
        return self::ownerOf($instance) ?? throw new MockException(
            'This ' . $instance::class . ' instance is not known to MicroUnit. Mock instances must be created '
            . 'with MicroMock::newInstance() and cannot be cloned.',
        );
    }
}
