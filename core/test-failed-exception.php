<?php

/**
 * Extend this class with custom behaviour if needed. 
 */

class TestFailedException extends Exception
{
    private const TEST_FAILED_CODE = 1000;

    public function __construct($message = "", ?Exception $previous = null)
    {
        parent::__construct($message, self::TEST_FAILED_CODE, $previous);
    }
}
