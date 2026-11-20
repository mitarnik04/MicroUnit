<?php

namespace MicroUnit\Exceptions;

/** Thrown by Assert::skip(): the test did not run, e.g. because a required service is unavailable. */
final class TestSkippedException extends \Exception {}
