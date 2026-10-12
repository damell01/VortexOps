<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Show;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds shows that are probably the same stream recorded twice — same
 * channel, same date, near-identical titles — before they double a week's
 * revenue in every report.
 *
 * Two shows with different Whatnot IDs that started more than half an hour
 * apart are two real streams (a "part 2"), so they are left out. A pair
 * someone has looked at and called "not a duplicate" is remembered and not
 * raised again.
 */
class DuplicateShowFinder
{
    public const LOOKBACK_DAYS = 120;
    public const MIN_SIMILARITY = 80;
    private const DISMISSED_KEY = 'dismissed_duplicate_show_pairs';

    /**
     * @return list<array{key: string, similarity: int, a: Show, b: Show}>
     */
    public function pairs(?int $days = self::LOOKBACK_DAYS): array
    {
        $dismissed = array_flip($this->dismissed());

        $shows = Show::query()
            ->whereNotIn('status', ['cancelled'])
            ->when($days, fn ($q) => $q->whereDate('show_date', '>=', now()->subDays($days)))
            // Upcoming shows have not reached any report yet.
            ->whereDate('show_date', '<=', now())
            ->with('channel:id,name')
            ->get(['id', 'title', 'show_date', 'start_time', 'whatnot_channel_id', 'whatnot_show_id', 'gross_revenue', 'units_sold', 'status']);

        $pairs = [];
        $shows->groupBy(fn (Show $s) => ($s->whatnot_channel_id ?? 'none').'|'.$s->show_date?->toDateString())
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use (&$pairs, $dismissed) {
                $group = $group->sortBy('id')->values();
                for ($i = 0; $i < $group->count(); $i++) {
                    for ($j = $i + 1; $j < $group->count(); $j++) {
                        [$a, $b] = [$group[$i], $group[$j]];
                        $key = $a->id.'-'.$b->id;
                        if (isset($dismissed[$key]) || $this->separateStreams($a, $b)) {
                            continue;
                        }
                        $similarity = $this->similarity($a->title, $b->title);
                        if ($similarity >= self::MIN_SIMILARITY) {
                            $pairs[] = ['key' => $key, 'similarity' => $similarity, 'a' => $a, 'b' => $b];
                        }
                    }
                }
            });

        usort($pairs, fn ($x, $y) => $y['a']->show_date <=> $x['a']->show_date);

        return $pairs;
    }

    public function count(): int
    {
        return count($this->pairs());
    }

    /** Remember that two shows were checked and are both real. */
    public function dismiss(string $key): void
    {
        abort_unless((bool) preg_match('/^\d+-\d+$/', $key), 422);
        $keys = $this->dismissed();
        $keys[] = $key;
        Setting::set(self::DISMISSED_KEY, json_encode(array_values(array_unique($keys))));
    }

    /** @return list<string> */
    private function dismissed(): array
    {
        $keys = json_decode((string) Setting::get(self::DISMISSED_KEY, '[]'), true);

        return is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];
    }

    private function separateStreams(Show $a, Show $b): bool
    {
        return $a->whatnot_show_id && $b->whatnot_show_id && $a->whatnot_show_id !== $b->whatnot_show_id
            && $a->start_time && $b->start_time
            && abs($a->start_time->diffInMinutes($b->start_time)) > 30;
    }

    /** 0–100: how alike two titles are once emoji, case and punctuation are ignored. */
    public function similarity(?string $a, ?string $b): int
    {
        $a = $this->squash($a);
        $b = $this->squash($b);
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 100;
        }
        similar_text($a, $b, $percent);

        return (int) round($percent);
    }

    private function squash(?string $title): string
    {
        return (string) Str::of((string) $title)->lower()->ascii()->replaceMatches('/[^a-z0-9$]+/', '');
    }
}
