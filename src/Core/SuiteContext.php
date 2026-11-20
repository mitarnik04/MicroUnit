<?php

namespace MicroUnit\Core;

/** What a suite's tests share while the suite runs: its Tester, the setUpOnce() value and shared fixtures. */
final readonly class SuiteContext
{
    public FixtureScope $sharedFixtures;

    public function __construct(
        public Tester $tester,
        public mixed $sharedValue = null,
    ) {
        $this->sharedFixtures = new FixtureScope();
    }
}
