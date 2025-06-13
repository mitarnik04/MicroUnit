<?php

namespace MicroUnit\Mock\Representation;

enum AccessModifier: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case PROTECTED = 'protected';
}
