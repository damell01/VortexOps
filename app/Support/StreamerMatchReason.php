<?php

namespace App\Support;

use App\Models\Show;
use App\Models\StreamerAlias;

/**
 * Why each streamer is on a show, worked out from the title: their name or one
 * of their aliases appears in it, or neither does and someone picked them. An
 * alias learned from an old show title can quietly tie a name to the wrong
 * person ("JAY" on Joshua's profile), and nothing on screen said so.
 */
class StreamerMatchReason
{
    /** @return list<string> one line per streamer, plus a warning when the title names someone else */
    public static function lines(Show $show): array
    {
        $title = StreamerAlias::normalize($show->title);
        $streamers = $show->streamers()->with('aliases')->get();
        $lines = [];
        $titleMatched = false;

        foreach ($streamers as $streamer) {
            $hit = null;
            if (self::inTitle($streamer->name, $title)) {
                $hit = 'their name is in the title';
            } else {
                foreach ($streamer->aliases as $alias) {
                    if (self::inTitle($alias->alias, $title)) {
                        $hit = "the alias “{$alias->alias}” on their profile is in the title (".self::source($alias->source).')';
                        break;
                    }
                }
            }
            $titleMatched = $titleMatched || $hit !== null;
            // What the importer that attached them wrote down at the time.
            $hit ??= collect(is_array($show->ai_streamer_suggestion) ? $show->ai_streamer_suggestion : [])
                ->first(fn ($s) => (int) ($s['streamer_id'] ?? 0) === $streamer->id && filled($s['reason'] ?? null))['reason'] ?? null;
            if ($hit !== null && ! str_starts_with($hit, 'their') && ! str_starts_with($hit, 'the alias')) {
                $hit = 'matched automatically when imported ('.$hit.')';
            }
            $lines[] = $streamer->name.': '.($hit ?? 'picked by hand — the title doesn’t name them');
        }

        $host = self::hostInTitle($show->title);
        if ($host && $streamers->isNotEmpty() && ! $titleMatched) {
            $lines[] = "The title names “{$host}”, which isn’t on any of these streamers’ profiles.";
        }

        return $lines;
    }

    /**
     * The name after "w/" or "with", allowing a space ("W/ JAY VCX"). Only for
     * this explanation: ShowHostDetection, which also creates profiles for
     * names it finds, stays strict.
     */
    private static function hostInTitle(?string $title): ?string
    {
        if (! preg_match('/(?:\bw\s*\/|\bwith\s+)\s*([\p{L}\p{N}][\p{L}\p{N} \x27.-]{0,40})/iu', (string) $title, $m)) {
            return null;
        }
        $name = trim(preg_replace('/\s+/', ' ', $m[1]), " .-");

        return $name !== '' ? $name : null;
    }

    private static function inTitle(?string $identity, string $title): bool
    {
        $needle = StreamerAlias::normalize($identity);

        return $needle !== '' && preg_match('/(?:^| )'.preg_quote($needle, '/').'(?: |$)/', $title) === 1;
    }

    private static function source(?string $source): string
    {
        return match ($source) {
            'show_title' => 'learned automatically from an earlier show title',
            'manual' => 'added by hand',
            default => $source ? 'from '.str_replace('_', ' ', $source) : 'source unknown',
        };
    }
}
