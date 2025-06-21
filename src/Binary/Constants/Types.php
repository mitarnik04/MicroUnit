<?php

namespace MicroUnit\Binary\Constants;

/** 
 * Defines number represantations of types in hex format.   
 * 
 * **Note:** Using constant containers instead of enums for performance 
 */
final class Types
{
    public const int NULL = 0x00;
    public const int FALSE = 0x01;
    public const int TRUE = 0x02;
    public const int INT = 0x03;
    public const int FLOAT = 0x04;
    public const int STRING = 0x05;
    public const int ARRAY_ASSOC = 0x06;
    public const int ARRAY_LIST = 0x07;
}
