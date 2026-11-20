<?php

namespace MicroUnit\Integration\Laravel;

use MicroUnit\Integration\HostedTestCaseIntegration;

/**
 * Runs every test inside Laravel's own test case, so the application, RefreshDatabase, fakes and
 * Mockery behave exactly as they do under PHPUnit.
 *
 *     ->withFramework(new LaravelIntegration(
 *         traits: [RefreshDatabase::class],
 *         environment: ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'],
 *     ))
 *
 * In tests: Laravel::getJson('/api/products')->assertOk(); Laravel::actingAs($user); ...
 */
final class LaravelIntegration extends HostedTestCaseIntegration
{
    private const string PROJECT_TEST_CASE = 'Tests\TestCase';

    private const string FRAMEWORK_TEST_CASE = 'Illuminate\Foundation\Testing\TestCase';

    /**
     * @param class-string|null $testCase default: the project's Tests\TestCase, else Laravel's base test case
     * @param list<class-string> $traits traits every test gets, e.g. RefreshDatabase
     * @param array<string, string> $environment like phpunit.xml's <env>; APP_ENV defaults to "testing", so
     *                                           RefreshDatabase never runs against the .env database
     */
    public function __construct(?string $testCase = null, array $traits = [], array $environment = [])
    {
        parent::__construct($testCase, $traits, ['APP_ENV' => 'testing', ...$environment]);
    }

    #[\Override]
    public function name(): string
    {
        $application = 'Illuminate\Foundation\Application';

        return 'Laravel ' . (defined("$application::VERSION") ? constant("$application::VERSION") : '');
    }

    /** The project's Tests\TestCase (with its helpers) when it exists, otherwise Laravel's base test case. */
    #[\Override]
    protected function defaultTestCase(): string
    {
        return class_exists(self::PROJECT_TEST_CASE) ? self::PROJECT_TEST_CASE : self::FRAMEWORK_TEST_CASE;
    }
}
