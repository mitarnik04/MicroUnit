<?php

namespace MicroUnit\Exceptions;

use MicroUnit\Assertion\AssertionFailure;

/** Thrown by every failing assertion. Extend it if you build custom assertions. */
class TestFailedException extends \Exception
{
    private const int TEST_FAILED_CODE = 1000;

    public function __construct(
        public readonly AssertionFailure $failure,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($failure->fullMessage(), self::TEST_FAILED_CODE, $previous);
    }
}
