<?php

namespace MicroUnit\Binary;

use MicroUnit\Binary\Constants\BitUnits;
use MicroUnit\Binary\Constants\Formats;
use MicroUnit\Binary\Constants\Types;


/**
 * All PHP methods are prefixed with `\` so PHP doesn't need to go looking for them in the custom namespace first,
 * which improves the performance (slightly)
 */
class BinarySerializer
{

    /** Serialize PHP value to custom binary format. */
    public static function serialize(mixed $value): string
    {
        if (is_null($value)) {
            return chr(Types::NULL);
        }
        if (is_bool($value)) {
            return self::boolToBinary($value);
        }
        if (is_int($value)) {
            // pack signed 64-bit integer, little endian
            return self::intToBinary($value);
        }
        if (is_float($value)) {
            return self::floatToBinary($value);
        }
        if (is_string($value)) {
            return self::stringToBinary($value);
        }
        if (is_array($value)) {
            if (self::isList($value)) {
                return self::ListToBinary($value);
            } else {
                // Assoc array
                return self::assocArrayToBinary($value);
            }
        }

        if (is_object($value)) {
            $array = (array)$value;
            return self::assocArrayToBinary($array);
        }

        throw new \InvalidArgumentException("Unsupported type for serialization: " . \gettype($value));
    }

    /**
     * Deserialize from custom binary format.
     * 
     */
    public static function deserialize(string $data, int &$offset = 0): mixed
    {
        if ($offset >= strlen($data)) {
            throw new \RuntimeException("BinarySerializer: Unexpected end of data");
        }

        $type = ord($data[$offset]);
        $offset++;

        switch ($type) {
            case Types::NULL:
                return null;

            case Types::FALSE:
                return false;

            case Types::TRUE:
                return true;

            case Types::INT:
                if ($offset + BitUnits::BYTE > \strlen($data)) {
                    throw new \RuntimeException("BinarySerializer: Unexpected end reading int");
                }
                $packedInt = substr($data, $offset,  BitUnits::BYTE);
                $int = unpack(Formats::UINT64, $packedInt)[1];
                $offset +=  BitUnits::BYTE;
                return $int;

            case Types::FLOAT:
                if ($offset +  BitUnits::BYTE > \strlen($data)) {
                    throw new \RuntimeException("BinarySerializer: Unexpected end reading float");
                }
                $packedFloat = substr($data, $offset,  BitUnits::BYTE);
                $float = unpack(Formats::DOUBLE, $packedFloat)[1];
                $offset +=  BitUnits::BYTE;
                return $float;

            case Types::STRING:
                if ($offset + BitUnits::HALF_BYTE > \strlen($data)) {
                    throw new \RuntimeException("BinarySerializer: Unexpected end reading string length");
                }
                $length = unpack(Formats::UINT32, substr($data, $offset, BitUnits::HALF_BYTE))[1];
                $offset += BitUnits::HALF_BYTE;
                if ($offset + $length > \strlen($data)) {
                    throw new \RuntimeException("BinarySerializer: Unexpected end reading string data");
                }
                $str = substr($data, $offset, $length);
                $offset += $length;
                return $str;

            case Types::ARRAY_LIST:
                if ($offset + BitUnits::HALF_BYTE > \strlen($data)) {
                    throw new \RuntimeException("BinarySerializer: Unexpected end reading list count");
                }
                $count = unpack(Formats::UINT32, substr($data, $offset, BitUnits::HALF_BYTE))[1];
                $offset += BitUnits::HALF_BYTE;
                $arr = [];
                for ($i = 0; $i < $count; $i++) {
                    $arr[] = self::deserialize($data, $offset);
                }
                return $arr;

            case Types::ARRAY_ASSOC:
                if ($offset + BitUnits::HALF_BYTE > \strlen($data)) {
                    throw new \RuntimeException("BinarySerializer: Unexpected end reading assoc count");
                }
                $count = unpack(Formats::UINT32, substr($data, $offset, BitUnits::HALF_BYTE))[1];
                $offset += BitUnits::HALF_BYTE;
                $arr = [];
                for ($i = 0; $i < $count; $i++) {
                    if ($offset + BitUnits::HALF_BYTE > \strlen($data)) {
                        throw new \RuntimeException("BinarySerializer: Unexpected end reading assoc key length");
                    }
                    $keyLen = \unpack(Formats::UINT32, \substr($data, $offset, BitUnits::HALF_BYTE))[1];
                    $offset += BitUnits::HALF_BYTE;
                    if ($offset + $keyLen > \strlen($data)) {
                        throw new \RuntimeException("BinarySerializer: Unexpected end reading assoc key");
                    }
                    $key = \substr($data, $offset, $keyLen);
                    $offset += $keyLen;
                    $value = self::deserialize($data, $offset);
                    $arr[$key] = $value;
                }
                return $arr;

            default:
                throw new \RuntimeException("BinarySerializer: Unknown data type marker: 0x" . \dechex($type));
        }
    }

    /** @param array<mixed,mixed> */
    private static function assocArrayToBinary(array $assoc)
    {
        $count = \count($assoc);
        $out = \chr(Types::ARRAY_ASSOC) . \pack(Formats::UINT32, $count);
        foreach ($assoc as $k => $v) {
            if (!is_string($k) && !is_int($k)) {
                throw new \InvalidArgumentException("Only string or int keys supported");
            }
            // Serialize key as string
            $keyStr = (string)$k;
            $out .= \pack(Formats::UINT32, strlen($keyStr)) . $keyStr;
            $out .= self::serialize($v);
        }

        return $out;
    }

    /** @param array<mixed> $list */
    private static function ListToBinary(array $list)
    {

        // List array
        $count = \count($list);
        $out = \chr(Types::ARRAY_LIST) . \pack(Formats::UINT32, $count);
        foreach ($list as $item) {
            $out .= self::serialize($item);
        }
        return $out;
    }

    private static function floatToBinary(float $value)
    {
        return \chr(Types::FLOAT) . \pack(Formats::DOUBLE, $value);
    }

    private static function intToBinary(int $value)
    {
        return \chr(Types::INT) . \pack(Formats::UINT64, $value);
    }

    private static function boolToBinary(bool $value)
    {
        if ($value === false) {
            return chr(Types::FALSE);
        }
        if ($value === true) {
            return chr(Types::TRUE);
        }
    }

    private static function stringToBinary(string $value)
    {
        $len = \strlen(BitUnits::HALF_BYTE);
        return \chr(Types::STRING) . \pack(Formats::UINT32, $len) . $value;
    }

    /**
     * Checks if array keys are sequential integers starting at 0.
     */
    private static function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
