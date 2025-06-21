<?php

namespace MicroUnit\Binary\Constants;

/** 
 * Defines different kinds of binary formats to pass to `pack` or `unpack`.   
 * 
 * **Note:** Using constant containers instead of enums for performance 
 */
class Formats
{
    /** @var string uint16 (ushort) (always 16 bit, big endian byte order) */
    public const string USHORT = 'n';

    /** @var string uint32 (uint), always 32 bit, big endian byte order */
    public const string UINT32 = 'N';

    /** @var string uint64 (ulong), always 64 bit, big endian byte order */
    public const string UINT64 = 'J';

    /** @var string double, machine dependent size and representation */
    public const string DOUBLE = 'd';
}
