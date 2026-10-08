<?php

namespace App\Services;

use App\Models\StreamerAlias;

class ShowHostDetection
{
    /** Explicit host markers and confirmed identities only; multiple hosts stay manual. */
    public function name(?string $title): ?string
    {
        $confirmed = collect(['Ashenway', 'B$'])->filter(fn ($name) => preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu', (string) $title) === 1)->values();
        if ($confirmed->count() > 1) return null;
        if ($confirmed->count() === 1) {
            if (preg_match('/[&+]|\band\b/iu', (string) $title)) return null;
            return $confirmed->first();
        }
        if (! preg_match('/(?:\bw\s*\/|\bwith\s+)([\p{L}\p{N}][\p{L}\p{N}\s\x27.-]*)/iu', (string) $title, $match)) return null;
        $name = trim(preg_replace('/\s+/', ' ', $match[1]), " .-");
        $tail = substr((string) $title, strpos((string) $title, $match[1]) + strlen($match[1]));
        if (preg_match('/\b(?:and|sale|starts|giveaways|boxes|breaks)\b/i', $name) || preg_match('/[&+]|\bw\s*\/|\bwith\s+/iu', $tail)) return null;
        return $name !== '' && mb_strlen($name) <= 60 && count(explode(' ', $name)) <= 4 ? $name : null;
    }

    public function key(string $name): string { return StreamerAlias::normalize($name); }
}
