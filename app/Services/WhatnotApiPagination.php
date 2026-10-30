<?php

namespace App\Services;

use RuntimeException;

/**
 * Extracts pagination tokens without guessing source-specific schema.
 * The caller must explicitly configure the response path after API validation.
 */
final class WhatnotApiPagination
{
    public static function nextCursor(array $payload, string $path): ?string
    {
        if (! in_array($path, ['next_cursor', 'pagination.next_cursor', 'meta.next_cursor'], true)) {
            throw new RuntimeException('Unsupported pagination cursor path.');
        }

        $value = $payload;
        foreach (explode('.', $path) as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || strlen($value) > 2048) {
            throw new RuntimeException('Invalid pagination cursor.');
        }

        return $value;
    }

    public static function assertUnseen(?string $cursor, array &$seen): void
    {
        if ($cursor === null) {
            return;
        }
        if (isset($seen[$cursor])) {
            throw new RuntimeException('Repeated pagination cursor detected.');
        }
        $seen[$cursor] = true;
    }
}
