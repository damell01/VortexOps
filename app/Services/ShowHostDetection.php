<?php

namespace App\Services;

use App\Models\StreamerAlias;

class ShowHostDetection
{
    /** Only explicit host markers qualify; multiple hosts stay in manual review. */
    public function name(?string $title): ?string
    {
        // Explicitly confirmed host identities can appear without a w/ or with marker.
        $confirmed = collect(['Ashenway', 'B'/(?:\bw\s*\/|\bwith\s+)([\p{L}\p{N}][\p{L}\p{N}\s\x27.-]*)/iu', (string) $title, $match)) return null;
        $name = trim(preg_replace('/\s+/', ' ', $match[1]), " .-");
        $tail = substr((string) $title, strpos((string) $title, $match[1]) + strlen($match[1]));
        if (preg_match('/\b(?:and|sale|starts|giveaways|boxes|breaks)\b/i', $name) || preg_match('/[&+]|\bw\s*\/|\bwith\s+/iu', $tail)) return null;
        return $name !== '' && mb_strlen($name) <= 60 && count(explode(' ', $name)) <= 4 ? $name : null;
    }

    public function key(string $name): string { return StreamerAlias::normalize($name); }
}
])->filter(fn ($name) => preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu', (string) $title) === 1)->values();
        if ($confirmed->count() > 1) return null;
        if ($confirmed->count() === 1) {
            // Keep multiple-host titles for manual assignment.
            if (preg_match('/(?:\bw\s*\/|\bwith\s+)(.*)/iu', (string) $title, $hosts) && preg_match('/[&+]|\band\b/iu', $hosts[1])) return null;
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
