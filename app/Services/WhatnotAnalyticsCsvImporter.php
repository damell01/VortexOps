<?php

namespace App\Services;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WhatnotAnalyticsCsvImporter
{
    // Only identity columns are mandatory. Whatnot changes the optional
    // analytics columns in its export over time, so available metrics are
    // imported without rejecting an otherwise valid Shows CSV.
    public const REQUIRED_HEADERS = ['Show', 'Date'];
    public function import(string $path, int $channelId, bool $dryRun = false): array
    {
        $channel = WhatnotChannel::findOrFail($channelId);
        $handle = fopen($path, 'rb');
        if (! $handle) throw new RuntimeException('Could not open the uploaded CSV.');

        // Whatnot puts its UTF-8 BOM before the opening quote of "Show".
        // Consume it before fgetcsv() so PHP recognizes that quote as the CSV
        // enclosure instead of returning a literal '"Show"' header.
        $prefix = fread($handle, 3);
        if ($prefix !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(static function ($header) {
            $header = trim((string) $header);
            $header = preg_replace('/^\\xEF\\xBB\\xBF/', '', $header) ?? $header;
            return trim($header, " \\t\\n\\r\\0\\x0B\\\"");
        }, $headers);

        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missing) {
            fclose($handle);
            throw new RuntimeException('This is not the expected Whatnot Shows export. Missing: '.implode(', ', $missing));
        }

        $stats = [
            'rows' => 0, 'updated' => 0, 'already_complete' => 0, 'ignored_current_future' => 0,
            'blank_metrics' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'no_shows' => 0,
            'exact_matched' => 0, 'date_tolerant_matched' => 0, 'fuzzy_matched' => 0,
        ];
        $unmatched = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (count($values) !== count($headers)) continue;
            $row = array_combine($headers, $values);
            $stats['rows']++;

            try { $date = Carbon::createFromFormat('m/d/Y', trim((string) $row['Date']))->startOfDay(); }
            catch (\Throwable) { $stats['unmatched']++; continue; }

            // Current-day/future rows are intentionally outside historical repair.
            if ($date->greaterThanOrEqualTo(today())) {
                $stats['ignored_current_future']++;
                continue;
            }

            $metrics = $this->metrics($row);
            if ($metrics === []) {
                $stats['blank_metrics']++;
                continue;
            }

            $title = trim((string) $row['Show']);
            [$show, $matchType, $ambiguous] = $this->matchShow($channel->id, $date, $title);

            if ($ambiguous) {
                $stats['ambiguous']++;
                continue;
            }
            if (! $show) {
                $stats['unmatched']++;
                if (count($unmatched) < 25) $unmatched[] = $date->toDateString().' · '.$title;
                continue;
            }
            $stats[$matchType.'_matched']++;
            $before = $show->only(array_keys($metrics));

            $duration = $metrics['show_duration'] ?? null;
            $oldZero = $duration === 0 && $date->lte(today()->subDays(2));

            if ($oldZero) {
                $metrics['status'] = 'cancelled';
                $metrics['analytics_sync_status'] = 'unavailable';
                $metrics['analytics_sync_note'] = 'Automatically excluded: Whatnot CSV reported a 0-minute duration at least 2 days after the scheduled show.';
                $metrics['analytics_unavailable_at'] = now();
                $stats['no_shows']++;
            } else {
                $metrics['analytics_sync_status'] = $this->complete($metrics) ? 'complete' : 'partial';
                $metrics['analytics_sync_note'] = 'Imported from Whatnot Seller Analytics CSV.';
                $metrics['analytics_unavailable_at'] = null;
            }
            $metrics['last_analytics_synced_at'] = now();

            $changed = collect($metrics)->contains(fn ($value, $key) => $this->different($show->getAttribute($key), $value));
            if (! $changed) {
                $stats['already_complete']++;
                continue;
            }

            if (! $dryRun) {
                DB::transaction(function () use ($show, $metrics, $before, $row, $channel, $oldZero) {
                    $show->forceFill($metrics)->save();

                    $changedFields = [];
                    foreach ($metrics as $key => $after) {
                        $prior = $before[$key] ?? null;
                        if ($this->different($prior, $after)) $changedFields[$key] = ['before' => $prior, 'after' => $after];
                    }

                    ShowIngestionLog::create([
                        'show_id' => $show->id,
                        'whatnot_channel_id' => $channel->id,
                        'source' => 'whatnot_analytics_csv',
                        'status' => 'success',
                        'raw_payload' => [
                            'event' => $oldZero ? 'no_show_excluded' : 'csv_analytics_import',
                            'changed_count' => count($changedFields),
                            'changed_fields' => $changedFields,
                            'csv' => $row,
                        ],
                    ]);
                });
            }
            $stats['updated']++;
        }

        fclose($handle);
        $stats['unmatched_examples'] = $unmatched;
        return $stats;
    }

    /**
     * Match conservatively in stages. Whatnot's export date can differ from our
     * stored date by one day around midnight/timezone boundaries, and historical
     * titles sometimes differ only by punctuation/emoji or a small edit.
     *
     * @return array{0:?Show,1:string,2:bool}
     */
    private function matchShow(int $channelId, Carbon $date, string $title): array
    {
        $needle = $this->normalize($title);
        $pool = Show::query()
            ->where('whatnot_channel_id', $channelId)
            ->whereBetween('show_date', [
                $date->copy()->subDay()->toDateString(),
                $date->copy()->addDay()->toDateString(),
            ])
            ->get();

        $sameDay = $pool->filter(fn (Show $show) => $show->show_date?->isSameDay($date));
        $exactSameDay = $sameDay->filter(fn (Show $show) => $this->normalize($show->title) === $needle)->values();
        if ($exactSameDay->count() === 1) return [$exactSameDay->first(), 'exact', false];
        if ($exactSameDay->count() > 1) return [null, 'exact', true];

        $exactNearby = $pool->filter(fn (Show $show) => $this->normalize($show->title) === $needle)->values();
        if ($exactNearby->count() === 1) return [$exactNearby->first(), 'date_tolerant', false];
        if ($exactNearby->count() > 1) return [null, 'date_tolerant', true];

        // Fuzzy matching is intentionally fail-closed: require a very strong
        // title score and a clear margin over the runner-up.
        $ranked = $pool->map(function (Show $show) use ($needle, $date) {
            $candidate = $this->normalize($show->title);
            similar_text($needle, $candidate, $score);
            if ($show->show_date?->isSameDay($date)) $score += 3.0;
            return ['show' => $show, 'score' => min(100.0, $score)];
        })->sortByDesc('score')->values();

        $best = $ranked->get(0);
        $second = $ranked->get(1);
        if ($best && $best['score'] >= 90.0 && (! $second || ($best['score'] - $second['score']) >= 8.0)) {
            return [$best['show'], 'fuzzy', false];
        }

        return [null, 'exact', false];
    }

    private function metrics(array $row): array
    {
        $map = [
            [['Est. Sales', 'Estimated Sales'], 'gross_revenue', 'money'],
            [['Est. Earning', 'Est. Earnings', 'Estimated Earnings'], 'whatnot_net', 'money'],
            [['Orders'], 'units_sold', 'int'],
            [['AOV', 'Average Order Value'], 'avg_order_value', 'money'],
            [['Giveaway Spend'], 'giveaway_spend', 'money'],
            [['Giveaways'], 'giveaways_count', 'int'],
            [['Buyers'], 'buyers_count', 'int'],
            [['First Time Buyers', 'First-Time Buyers'], 'first_time_buyers', 'int'],
            [['Returning Buyers'], 'returning_buyers', 'int'],
            [['Show Shares', 'Shares'], 'shares_count', 'int'],
            [['Show Duration (mins)', 'Show Duration', 'Duration (mins)'], 'show_duration', 'int'],
            [['Max Concurrent Viewers'], 'max_concurrent_viewers', 'int'],
            [['Total Views'], 'total_views', 'int'],
            [['Average Order Rating', 'Avg. Order Rating'], 'avg_order_rating', 'float'],
        ];

        $out = [];
        foreach ($map as [$columns, $field, $type]) {
            $raw = null;
            foreach ($columns as $column) {
                if (array_key_exists($column, $row)) {
                    $raw = trim((string) $row[$column]);
                    break;
                }
            }
            if ($raw === null || $raw === '' || $raw === '—' || $raw === '-') continue;
            $clean = str_replace([',', '$'], '', $raw);
            if (! is_numeric($clean)) continue;
            $out[$field] = match ($type) {
                'int' => (int) round((float) $clean),
                default => (float) $clean,
            };
        }
        return $out;
    }

    private function complete(array $metrics): bool
    {
        return array_key_exists('gross_revenue', $metrics)
            && array_key_exists('show_duration', $metrics)
            && (array_key_exists('whatnot_net', $metrics) || array_key_exists('completed_earnings', $metrics));
    }

    private function normalize(?string $value): string
    {
        return (string) Str::of((string) $value)->lower()->squish()->replaceMatches('/[^\\pL\\pN]+/u', '');
    }

    private function different(mixed $a, mixed $b): bool
    {
        if ($a instanceof \DateTimeInterface) $a = $a->format('Y-m-d H:i:s');
        if ($b instanceof \DateTimeInterface) $b = $b->format('Y-m-d H:i:s');
        if (is_numeric($a) && is_numeric($b)) return abs((float) $a - (float) $b) > 0.0001;
        return (string) ($a ?? '') !== (string) ($b ?? '');
    }
}
