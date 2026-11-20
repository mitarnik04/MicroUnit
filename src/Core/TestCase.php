<?php

namespace MicroUnit\Core;

/** One data set for Tester::defineGroup(). */
final readonly class TestCase
{
    /**
     * @param string $testNameSuffix appended to the group's base name: "{base}_{suffix}"
     * @param mixed $params arguments for the test function; an array is spread, anything else is passed as the only argument
     */
    public function __construct(
        public string $testNameSuffix,
        public mixed $params = [],
    ) {}
}
