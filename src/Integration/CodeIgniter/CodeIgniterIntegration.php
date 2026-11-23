<?php

namespace MicroUnit\Integration\CodeIgniter;

use MicroUnit\Helpers\Filesystem;
use MicroUnit\Integration\HostedTestCaseIntegration;

/**
 * Runs every test inside CodeIgniter 4's CIUnitTestCase, with the traits you need:
 *
 *     ->withFramework(new CodeIgniterIntegration(traits: [FeatureTestTrait::class, DatabaseTestTrait::class]))
 *
 * In tests: CodeIgniter::get('/')->assertOK(); CodeIgniter::seeInDatabase('users', [...]); ...
 */
final class CodeIgniterIntegration extends HostedTestCaseIntegration
{
    private const string TEST_BOOTSTRAP = 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

    /**
     * @param class-string|null $testCase defaults to CodeIgniter\Test\CIUnitTestCase
     * @param list<class-string> $traits e.g. FeatureTestTrait, DatabaseTestTrait
     * @param array<string, string> $environment CI_ENVIRONMENT defaults to "testing"
     */
    public function __construct(?string $testCase = null, array $traits = [], array $environment = [])
    {
        parent::__construct($testCase, $traits, ['CI_ENVIRONMENT' => 'testing', ...$environment]);
    }

    #[\Override]
    public function name(): string
    {
        return 'CodeIgniter ' . self::version('CodeIgniter\CodeIgniter::CI_VERSION');
    }

    /** CodeIgniter needs its test bootstrap (paths, constants, services) before anything runs. */
    #[\Override]
    public function boot(string $projectDirectory): void
    {
        parent::boot($projectDirectory);

        $bootstrap = Filesystem::findUpwards(self::TEST_BOOTSTRAP, $projectDirectory);
        if ($bootstrap === null) {
            throw new \LogicException('CodeIgniter 4 was not found: expected ' . self::TEST_BOOTSTRAP . ' in the project.');
        }
        // phpunit.xml normally defines HOMEPATH; without it the bootstrap guesses from the working directory.
        defined('HOMEPATH') || define('HOMEPATH', dirname($bootstrap, 6) . DIRECTORY_SEPARATOR);
        require_once $bootstrap;
    }

    #[\Override]
    protected function defaultTestCase(): string
    {
        return \CodeIgniter\Test\CIUnitTestCase::class;
    }
}
