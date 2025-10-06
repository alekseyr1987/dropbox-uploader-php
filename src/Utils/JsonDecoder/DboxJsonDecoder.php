<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\Utils\JsonDecoder;

/**
 * Utility class for decoding JSON strings into associative arrays, returning either decoded data or a default value depending on success.
 */
final class DboxJsonDecoder
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Decodes a JSON string into an associative array.
     *
     * If decoding fails or the result is not an array:
     * - Returns `$default` if it is non-empty.
     * - Throws `UnexpectedValueException` if `$default` is empty.
     *
     * The optional `$path` parameter is used to indicate the file path in error messages when decoding fails.
     *
     * @param string               $content JSON string to decode
     * @param array<string, mixed> $default Array to return if decoding fails; if empty, an exception is thrown
     * @param ?string              $path    Optional file path to include in exception messages
     *
     * @return array<string, mixed> Decoded associative array or `$default` if provided
     */
    public static function decode(string $content, array $default, ?string $path = null): array
    {
        $data = json_decode($content, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            if (!empty($default)) {
                return $default;
            }

            throw new \UnexpectedValueException("Invalid JSON format in token file: '{$path}'.");
        }

        if (!is_array($data)) {
            if (!empty($default)) {
                return $default;
            }

            throw new \UnexpectedValueException("Decoded token file is not an array: '{$path}'.");
        }

        return $data; // @phpstan-ignore return.type
    }
}
