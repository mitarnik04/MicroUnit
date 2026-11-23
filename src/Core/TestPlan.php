<?php

namespace MicroUnit\Core;

/** The ordered list of tests a run will execute. */
final readonly class TestPlan
{
    /** @param list<array{Tester, Test}> $entries in definition order */
    private function __construct(
        public array $entries,
    ) {}

    /** @param list<Tester> $testers */
    public static function create(array $testers, TestFilter $filter): self
    {
        $entries = [];
        foreach ($testers as $tester) {
            foreach ($tester->getTests() as $test) {
                if ($filter->accepts($test)) {
                    $entries[] = [$tester, $test];
                }
            }
        }

        return new self($entries);
    }

    /**
     * The share of one parallel worker. Whole suites are the unit of distribution, so a suite's
     * setUpOnce, shared fixtures and any resources its tests share stay in one process. Suites are
     * dealt largest first to the least loaded worker; every process computes the same split.
     *
     * @param int<1, max> $workerCount
     */
    public function partition(int $workerIndex, int $workerCount): self
    {
        $suites = $this->bySuite();
        $order = array_keys($suites);
        usort($order, static fn(int $a, int $b): int => count($suites[$b][1]) <=> count($suites[$a][1]) ?: $a <=> $b);

        $load = array_fill(0, $workerCount, 0);
        $mine = [];
        foreach ($order as $suiteIndex) {
            $worker = array_search(min($load), $load, true);
            $load[$worker] += count($suites[$suiteIndex][1]);
            if ($worker === $workerIndex) {
                $mine[$suiteIndex] = true;
            }
        }

        $entries = [];
        foreach ($suites as $suiteIndex => [$tester, $tests]) {
            if (isset($mine[$suiteIndex])) {
                foreach ($tests as $test) {
                    $entries[] = [$tester, $test];
                }
            }
        }

        return new self($entries);
    }

    /** @return list<array{Tester, list<Test>}> consecutive entries grouped by suite, in plan order */
    public function bySuite(): array
    {
        $groups = [];
        foreach ($this->entries as [$tester, $test]) {
            $last = array_key_last($groups);
            if ($last === null || $groups[$last][0] !== $tester) {
                $groups[] = [$tester, []];
                $last = array_key_last($groups);
            }
            $groups[$last][1][] = $test;
        }

        return $groups;
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
