<?php

namespace MicroUnit\Exceptions;

use MicroUnit\Assertion\AssertionFailure;

/**
 * Extend this class with custom behaviour if needed. 
 */

class TestFailedException extends \Exception
{
    private const TEST_FAILED_CODE = 1000;

    public function __construct(public readonly AssertionFailure $failure, ?\Exception $previous = null)
    {
        parent::__construct($failure->message, self::TEST_FAILED_CODE, $previous);
    }
}
