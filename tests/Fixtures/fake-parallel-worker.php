<?php

// Stands in for a parallel worker: writes a result file in WorkerRunner's format with one readable result,
// one that cannot be unserialized and one for a test that belongs to another worker.
// Usage: php fake-parallel-worker.php <output file>

use MicroUnit\Core\TestResult;
use MicroUnit\Core\TestStatus;

require dirname(__DIR__, 2) . '/src/Bootstrap/Autoloader.php';
MicroUnit\Bootstrap\Autoloader::register();

$passed = new TestResult('Sample::reads_fine', 'Sample', 'reads_fine', TestStatus::Passed, 0.001);

file_put_contents($argv[1], serialize([
    'results' => [
        'Sample::reads_fine' => serialize($passed),
        'Sample::arrives_broken' => 'O:99:"Broken',
        'Other::not_in_this_share' => serialize($passed),
    ],
    'coverage' => serialize(null),
]));
