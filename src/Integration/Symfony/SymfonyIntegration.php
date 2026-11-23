<?php

namespace MicroUnit\Integration\Symfony;

use MicroUnit\Integration\HostedTestCaseIntegration;

/**
 * Runs every test inside Symfony's WebTestCase, so kernel booting, the test container and the
 * BrowserKit client behave exactly as they do under PHPUnit.
 *
 *     ->withBootstrapFile('tests/bootstrap.php')
 *     ->withFramework(new SymfonyIntegration(environment: ['KERNEL_CLASS' => 'App\Kernel']))
 *
 * In tests: Symfony::client()->request('GET', '/'); Symfony::assertResponseIsSuccessful(); ...
 */
final class SymfonyIntegration extends HostedTestCaseIntegration
{
    /**
     * @param class-string|null $testCase defaults to Symfony\Bundle\FrameworkBundle\Test\WebTestCase
     * @param list<class-string> $traits
     * @param array<string, string> $environment APP_ENV defaults to "test"
     */
    public function __construct(?string $testCase = null, array $traits = [], array $environment = [])
    {
        parent::__construct($testCase, $traits, ['APP_ENV' => 'test', ...$environment]);
    }

    #[\Override]
    public function name(): string
    {
        return 'Symfony ' . self::version('Symfony\Component\HttpKernel\Kernel::VERSION');
    }

    #[\Override]
    protected function defaultTestCase(): string
    {
        return \Symfony\Bundle\FrameworkBundle\Test\WebTestCase::class;
    }
}
