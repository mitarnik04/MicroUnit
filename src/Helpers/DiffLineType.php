<?php

namespace MicroUnit\Helpers;

enum DiffLineType: string
{
    case Same = 'same';
    case ExpectedOnly = 'expected';
    case ActualOnly = 'actual';
}
