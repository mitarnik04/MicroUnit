<?php
require_once __DIR__ . '/tester.php';
require_once __DIR__ . '/../output/test-writer.php';
require_once __DIR__ . '/test-result.php';


/** 
 * Use this hub to get a tester instance for a specific suite
 * and execute all tests contained in all testers provided by this hub
 */
class TesterHub
{
    public function __construct(
        public WriteHelper $writer,
        private bool $stopOnFailure,
    ) {}

    /** @var array<Tester> */
    private array $testers = [];

    private int $totalTests = 0;
    private int $succeeded = 0;
    private int $failed = 0;

    public function getOrCreateTester(string $suite): Tester
    {
        $tester = $this->testers[$suite] ?? null;
        if (isset($tester)) {
            return $tester;
        }
        $tester = new Tester($this->stopOnFailure);
        $this->testers[$suite] = $tester;
        return $tester;
    }

    public function runAll()
    {
        foreach ($this->testers as $suite => $tester) {
            if ($this->stopOnFailure && $this->failed > 0) {
                break;
            }

            $this->writer->writeSuite($suite);

            $results = $tester->run();
            $this->writer->writeResults($results);

            $counts = $this->getCounts($results);

            $this->updateMetrics($counts);
        }

        $this->writer->writeSummary($this->totalTests, $this->succeeded, $this->failed);
        exit($this->failed > 0 ? 1 : 0);
    }

    /** @param array<TestResult> $results */
    private function getCounts(array $results)
    {
        return array_reduce($results, function ($carry, $result) {
            $carry['total']++;
            if ($result->isSuccess) {
                $carry['succeeded']++;
            } else {
                $carry['failed']++;
            }
            return $carry;
        }, ['total' => 0, 'succeeded' => 0, 'failed' => 0]);
    }

    private function updateMetrics(array $counts)
    {
        $this->totalTests += $counts['total'];
        $this->succeeded += $counts['succeeded'];
        $this->failed += $counts['failed'];
    }
}
