<?php

namespace MicroUnit\Integration;

/**
 * Generates a small subclass of a framework's base test case (plus traits such as Laravel's
 * RefreshDatabase) so the framework's own setUp()/tearDown() and testing helpers run exactly as
 * under PHPUnit. The framework classes are only referenced at runtime: MicroUnit itself depends on
 * none of them.
 *
 * @internal
 */
final class TestCaseHost
{
    private const string GENERATED_NAMESPACE = 'MicroUnit\Integration\Generated';

    /** @var array<string, class-string<IHostedTestCase>> shape => generated class */
    private static array $classes = [];

    /** PHPUnit's configuration is set up once per process; afterwards nothing needs checking per test. */
    private static bool $isPhpUnitConfigurationChecked = false;

    private function __construct() {}

    /**
     * @param class-string $baseClass e.g. Illuminate\Foundation\Testing\TestCase or your Tests\TestCase
     * @param list<class-string> $traits
     */
    public static function create(string $baseClass, array $traits): IHostedTestCase
    {
        if (!class_exists($baseClass)) {
            throw new \LogicException("The test case class $baseClass does not exist. Is the framework (and phpunit/phpunit) installed?");
        }

        self::initializePhpUnitConfiguration();
        $shape = $baseClass . '|' . implode(',', $traits);
        if (!isset(self::$classes[$shape])) {
            $class = self::$classes[$shape] = self::declare($baseClass, $traits);
            // Class-level hooks run once per host class, as PHPUnit runs them once per test class
            // (CodeIgniter loads its test helpers there, for example).
            $class::setUpBeforeClass();
            register_shutdown_function(static fn() => $class::tearDownAfterClass());
        }

        return new (self::$classes[$shape])('microunit');
    }

    /**
     * Framework test cases hand error handling back to PHPUnit's runner on tearDown, which reads
     * PHPUnit's configuration registry. Outside PHPUnit's runner nobody initialises it, so we do,
     * with PHPUnit's own defaults (exactly what it uses without a phpunit.xml).
     */
    private static function initializePhpUnitConfiguration(): void
    {
        if (self::$isPhpUnitConfigurationChecked) {
            return;
        }
        self::$isPhpUnitConfigurationChecked = true;

        $registry = \PHPUnit\TextUI\Configuration\Registry::class;
        if (!class_exists($registry) || new \ReflectionProperty($registry, 'instance')->getValue() !== null) {
            return;
        }

        // PHPUnit 13 hands its event emitter to the CLI builder and the registry; 10 to 12 take none.
        $emitter = new \ReflectionMethod($registry, 'init')->getNumberOfParameters() > 2 ? [\PHPUnit\Event\Facade::emitter()] : [];
        $cliArguments = new \PHPUnit\TextUI\CliArguments\Builder(...$emitter)->fromParameters([]);
        $registry::init($cliArguments, \PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration::create(), ...$emitter);
    }

    /**
     * @param list<class-string> $traits
     * @return class-string<IHostedTestCase>
     */
    private static function declare(string $baseClass, array $traits): string
    {
        $shortName = 'Host' . count(self::$classes);
        $uses = $traits === [] ? '' : 'use \\' . implode(', \\', array_map(static fn(string $trait): string => ltrim($trait, '\\'), $traits)) . ';';

        eval(sprintf(
            'namespace %s; final class %s extends \\%s implements \\%s { %s
                public function setUpForMicroUnit(): void { $this->setUp(); }
                public function tearDownForMicroUnit(): void { $this->tearDown(); }
                public function callForMicroUnit(string $method, array $arguments): mixed {
                    if (!method_exists($this, $method) && !method_exists($this, "__call")) {
                        throw new \BadMethodCallException(sprintf("%%s has no method %%s(). Is a trait missing? Add it with the proxy\'s uses().", get_parent_class($this), $method));
                    }
                    return $this->{$method}(...$arguments);
                }
            }',
            self::GENERATED_NAMESPACE,
            $shortName,
            ltrim($baseClass, '\\'),
            IHostedTestCase::class,
            $uses,
        ));

        /** @var class-string<IHostedTestCase> $class declared by the eval above */
        $class = self::GENERATED_NAMESPACE . '\\' . $shortName;

        return $class;
    }
}
