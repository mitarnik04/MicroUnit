<?php

namespace MicroUnit\Mock;

enum ExpectationKind: string
{
    case TIMES = 'times';
    case ARGS = 'arg';
}
