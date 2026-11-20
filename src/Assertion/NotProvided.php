<?php

namespace MicroUnit\Assertion;

/** Marks an expected/actual slot of an AssertionFailure as "nothing to show" (null is a real value). */
enum NotProvided
{
    case Value;
}
