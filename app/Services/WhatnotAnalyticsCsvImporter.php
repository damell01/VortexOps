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
    public const REQUIRED_HEADERS = [
        'Show', 'Date', 'Est. Sales', 'Est. Earning', 'Orders', 'AOV',
        'Giveaway Spend', 'Giveaways', 'Buyers', 'First Time Buyers',
        'Returning Buyers', 'Show Shares', 'Show Duration (mins)',
        'Max Concurrent Viewers', 'Total Views', 'Unique Viewers',
        'Average Order Rating',
    ];

    public function import(string $path, int $channelId, bool $dryRun = false): array
    {
        $channel = WhatnotChannel::findOrFail($channelId);
        $handle = fopen($path, 'rb');
        if (! $handle) throw new RuntimeException('Could not open the uploaded CSV.');

        $headers = fgetcsv($handle) ?: [];
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missing) {
            fclose($handle);
            throw new RuntimeException('This is not the expected Whatnot Shows export. Missing: '.implode(', ', $missing));
        }

        $stats = [
            'rows' => 0, 'updated' => 0, 'already_complete' => 0, 'ignored_current_future' => 0,
            'blank_metrics' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'no_shows' => 0,
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
            $candidates = Show::query()
                ->where('whatnot_channel_id', $channel->id)
                ->whereDate('show_date', $date->toDateString())
                ->get()
                ->filter(fn (Show $show) => $this->normalize($show->title) === $this->normalize($title))
                ->values();

            if ($candidates->count() === 0) {
                $stats['unmatched']++;
                if (count($unmatched) < 25) $unmatched[] = $date->toDateString().' · '.$title;
                continue;
            }
            if ($candidates->count() > 1) {
                $stats['ambiguous']++;
                continue;
            }

            /** @var Show $show */
            $show = $candidates->first();
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

    private function metrics(array $row): array
    {
        $map = [
            'Est. Sales' => ['gross_revenue', 'money'],
            'Est. Earning' => ['whatnot_net', 'money'],
            'Orders' => ['units_sold', 'int'],
            'AOV' => ['avg_order_value', 'money'],
            'Giveaway Spend' => ['giveaway_spend', 'money'],
            'Giveaways' => ['giveaways_count', 'int'],
            'Buyers' => ['buyers_count', 'int'],
            'First Time Buyers' => ['first_time_buyers', 'int'],
            'Returning Buyers' => ['returning_buyers', 'int'],
            'Show Shares' => ['shares_count', 'int'],
            'Show Duration (mins)' => ['show_duration', 'int'],
            'Max Concurrent Viewers' => ['max_concurrent_viewers', 'int'],
            'Total Views' => ['total_views', 'int'],
            'Average Order Rating' => ['avg_order_rating', 'float'],
        ];

        $out = [];
        foreach ($map as $column => [$field, $type]) {
            $raw = trim((string) ($row[$column] ?? ''));
            if ($raw === '' || $raw === '—' || $raw === '-') continue;
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
