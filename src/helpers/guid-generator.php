<?php

class GuidGenerator
{
    public static function generateV4()
    {
        // Generate 16 bytes (128 bits) of random data
        $data = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // Set variant to 10xx (RFC 4122 variant)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        // Format as UUID string
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
