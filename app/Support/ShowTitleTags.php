<?php

namespace App\Support;

/**
 * Reads what a show offered from its title: "🍀MASSIVE GIVVYS AND $1 STARTS
 * W/NIKO🍀" → giveaways, $1 starts.
 *
 * Plain patterns, no AI model: titles are short and written the same few ways,
 * so rules are instant, free and predictable. Spellings come from real titles
 * (givvys, givy's, givey, giveaway). Bump VERSION whenever a rule changes so
 * `shows:tag-titles` re-tags the shows the old rules produced.
 */
final class ShowTitleTags
{
    public const VERSION = 1;

    /** @var array<string, array{label: string, pattern: string}> */
    private const TAGS = [
        'giveaways' => [
            'label' => 'Giveaways',
            'pattern' => "/\\bgiv(?:e\\s*aways?|v?(?:e?y|ie)(?:'?s)?|vys|s)\\b/iu",
        ],
        'dollar_starts' => [
            'label' => '$1 starts',
            'pattern' => '/\$\s*1\s*(?:starts?|auctions?)\b|\b(?:1|one)\s*dollar\s*starts?\b|\bpenny\s*starts?\b/iu',
        ],
        'free_packs' => [
            'label' => 'Free packs',
            'pattern' => '/\bfree\s+(?:booster\s+)?packs?\b/iu',
        ],
        'pop_up' => [
            'label' => 'Pop-up',
            'pattern' => '/\bpop[\s-]?ups?\b/iu',
        ],
        'sudden_death' => [
            'label' => 'Sudden death',
            'pattern' => '/\bsudden\s*death\b/iu',
        ],
        'mystery' => [
            'label' => 'Mystery',
            'pattern' => '/\bmystery\b/iu',
        ],
        'case_break' => [
            'label' => 'Case / box break',
            'pattern' => '/\b(?:case|box)\s*breaks?\b/iu',
        ],
    ];

    /** @return list<string> tag keys found in the title, in a fixed order */
    public static function for(?string $title): array
    {
        $title = (string) $title;
        $tags = [];
        foreach (self::TAGS as $key => $tag) {
            if (preg_match($tag['pattern'], $title)) {
                $tags[] = $key;
            }
        }

        return $tags;
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_map(fn (array $tag) => $tag['label'], self::TAGS);
    }

    public static function label(string $key): string
    {
        return self::TAGS[$key]['label'] ?? $key;
    }
}
