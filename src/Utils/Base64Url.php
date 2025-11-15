<?php

namespace HeadlessCart\Utils;

if (!defined('ABSPATH')) exit;

/**
 * Small helper for RFC 7515 compatible base64url encoding/decoding.
 *
 * Used by the token generator to build compact JWT-like strings
 * without relying on external libraries.
 */
class Base64Url
{
    /**
     * Encode arbitrary binary/string data into base64url.
     *
     * @param string $data
     * @return string
     */
    public static function encode(string $data): string
    {
        $b64 = base64_encode($data);
        // Replace +/ with -_ and strip padding =
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }

    /**
     * Decode base64url back to raw binary/string.
     *
     * @param string $data
     * @return string|false
     */
    public static function decode(string $data)
    {
        $b64 = strtr($data, '-_', '+/');
        // Pad with = to a multiple of 4
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($b64);
    }
}
