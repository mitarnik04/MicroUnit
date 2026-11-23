<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\Tester;
use MicroUnit\Core\TestFilter;
use MicroUnit\Core\TestPlan;
use MicroUnit\Core\TestStatus;
use MicroUnit\Parallel\ParallelRunner;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('ParallelRunner');

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

$tester->define('a_result_that_cannot_be_read_fails_only_its_own_test', function (TemporaryDirectory $directory): void {
    $sample = new Tester('Sample');
    $sample->define('reads_fine', fn() => null);
    $sample->define('arrives_broken', fn() => null);
    $fakeWorker = dirname(__DIR__, 2) . '/Fixtures/fake-parallel-worker.php';
    $runner = new ParallelRunner(fn(int $index, int $count, string $outputFile): array => [PHP_BINARY, $fakeWorker, $outputFile], 2, $directory->root);

    $results = $runner->run(TestPlan::create([$sample], TestFilter::all()), static function (): void {})->summary->results;

    Assert::exact(['Sample::reads_fine', 'Sample::arrives_broken'], array_map(fn($result) => $result->id, $results));
    Assert::exact(TestStatus::Passed, $results[0]->status);
    Assert::exact(TestStatus::Failed, $results[1]->status);
    Assert::stringContains('its result could not be read: unserialize(): Error at offset', (string) $results[1]->errorMsg);
});
