<?php

namespace MicroUnit\Mock\Representation;

enum TypeCombination: string
{
    case UNION = '|';
    case INTERSECTION = '&';
}
