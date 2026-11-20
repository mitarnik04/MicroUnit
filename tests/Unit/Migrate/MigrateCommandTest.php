<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Helpers\PhpProcess;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('MigrateCommand');

$tester->fixture('project', fn(): TemporaryDirectory => TemporaryDirectory::create('microunit-migrate'), cleanup: fn(TemporaryDirectory $project) => $project->delete());

/**
 * Runs `microunit migrate` in a directory, like a user would.
 *
 * @param list<string> $arguments
 * @return array{int, string, string} exit code, output, errors
 */
function runMigrate(string $directory, array $arguments = []): array
{
    $process = proc_open(
        [...PhpProcess::command(dirname(__DIR__, 3) . '/bin/microunit'), 'migrate', '--no-agent', '--no-colors', ...$arguments],
        [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $directory,
    );
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);

    return [proc_close($process), $output, $errors];
}

$phpUnitTest = static fn(string $class, string $namespace = 'Tests'): string => <<<PHP
    namespace $namespace;
    use PHPUnit\Framework\TestCase;
    final class $class extends TestCase
    {
        public function testWorks(): void
        {
            \$this->assertTrue(true);
        }
    }
    PHP;

$tester->define('several_test_classes_in_one_file_get_a_file_each', function (TemporaryDirectory $project) use ($phpUnitTest): void {
    $project->write('tests/PairTest.php', "<?php\n" . $phpUnitTest('FooTest') . "\n" . $phpUnitTest('BarCase'));

    [$exitCode, $output] = runMigrate($project->root);

    Assert::exact(0, $exitCode, $output);
    Assert::stringContains("TestSetup::getTester('Foo')", $project->read('tests-microunit/FooTest.php'));
    Assert::stringContains("TestSetup::getTester('BarCase')", $project->read('tests-microunit/BarCaseTest.php'));
    Assert::isFalse($project->exists('tests-microunit/PairTest.php'));
});

$tester->define('classes_that_are_no_test_cases_are_kept_as_support_code', function (TemporaryDirectory $project) use ($phpUnitTest): void {
    $project->write('tests/CartTest.php', "<?php\nnamespace Acme;\nclass Tester\n{\n    public function test(): void {}\n}\n" . $phpUnitTest('CartTest'));

    runMigrate($project->root);

    Assert::isFalse($project->exists('tests-microunit/TesterTest.php'));
    $support = $project->read('tests-microunit/CartTest.support.php');
    Assert::stringContains("namespace Acme {\n", $support);
    Assert::stringContains('class Tester', $support);
});

$tester->define('the_bootstrap_is_required_from_an_output_directory_outside_the_project', function (TemporaryDirectory $project) use ($phpUnitTest): void {
    $project->write('app/phpunit.xml', '<phpunit bootstrap="./tests/bootstrap.php"><php><env name="APP_ENV" value="test"/></php></phpunit>');
    $project->write('app/tests/bootstrap.php', '<?php');
    $project->write('app/tests/CartTest.php', "<?php\n" . $phpUnitTest('CartTest'));

    runMigrate($project->path('app'), ['tests', '--out=../migrated']);

    Assert::stringContains("require __DIR__ . '/../app/tests/bootstrap.php';", $project->read('migrated/bootstrap.php'));
});

$tester->define('a_base_class_that_cannot_be_parsed_is_skipped_with_a_message', function (TemporaryDirectory $project): void {
    $project->write('composer.json', '{"autoload-dev": {"psr-4": {"Support\\\\": "support/"}}}');
    $project->write('support/BaseTest.php', '<?php namespace Support; abstract class BaseTest extends \PHPUnit\Framework\TestCase { public function broken( }');
    $project->write('tests/CartTest.php', "<?php\nnamespace Tests;\nfinal class CartTest extends \Support\BaseTest\n{\n    public function testWorks(): void\n    {\n        \$this->assertTrue(true);\n    }\n}\n");

    [$exitCode, $output, $errors] = runMigrate($project->root);

    Assert::exact(0, $exitCode, $output . $errors);
    Assert::stringContains('BaseTest.php', $errors);
    Assert::isTrue($project->exists('tests-microunit/CartTest.php'));
});
