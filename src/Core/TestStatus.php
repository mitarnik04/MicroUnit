<?php

namespace MicroUnit\Core;

enum TestStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
