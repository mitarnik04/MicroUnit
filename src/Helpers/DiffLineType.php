<?php

namespace MicroUnit\Helpers;

enum DiffLineType
{
    case Same;
    case ExpectedDifferent;
    case ActualDifferent;
}
