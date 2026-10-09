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
    public const REQUIRED_HEADERS = ['Show', 'Date'];

    public function import(string $path, int $channelId, bool $dryRun = false, string $since = '2026-07-01', array $blockedIdentities = []): array
    {
        $channel = WhatnotChannel::findOrFail($channelId);
        $blockedIdentities = array_fill_keys(array_unique(array_merge($blockedIdentities, $this->conflicts([$path]))), true);
        $cutoff = Carbon::createFromFormat('!Y-m-d', $since);
        if (! $cutoff || $cutoff->format('Y-m-d') !== $since) throw new RuntimeException('Invalid reporting start date.');
        $handle = fopen($path, 'rb');
        if (! $handle) throw new RuntimeException('Could not open the uploaded CSV.');
        $stats = array_fill_keys(['rows', 'created', 'updated', 'already_complete', 'ignored_current_future', 'before_start', 'blank_metrics', 'unmatched', 'ambiguous', 'no_shows', 'exact_matched', 'date_tolerant_matched', 'fuzzy_matched'], 0);
        $stats['examples'] = [];
        $stats['unmatched_examples'] = [];
        $stats['date_from'] = null;
        $stats['date_to'] = null;
        $seen = [];

        try {
            if (fread($handle, 3) !== "\xEF\xBB\xBF") rewind($handle);
            $headers = fgetcsv($handle, 0, ',', '"', '') ?: [];
            $headers = array_map(fn ($h) => trim((string) $h), $headers);
            $missing = array_diff(self::REQUIRED_HEADERS, $headers);
            if ($missing) throw new RuntimeException('Expected a Whatnot Shows CSV. Missing: '.implode(', ', $missing));
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($values === [null]) continue;
                $stats['rows']++;
                if (count($values) !== count($headers)) { $stats['unmatched']++; continue; }
                $row = array_combine($headers, $values);
                $title = trim((string) $row['Show']);
                try {
                    $date = Carbon::createFromFormat('!m/d/Y', trim((string) $row['Date']));
                    if (! $date || $date->format('m/d/Y') !== trim((string) $row['Date']) || $title === '') throw new RuntimeException('Invalid title/date.');
                } catch (\Throwable) { $stats['unmatched']++; continue; }
                if ($date->lt($cutoff)) { $stats['before_start']++; continue; }
                if ($date->gt(today())) { $stats['ignored_current_future']++; continue; }
                $day = $date->toDateString();
                $stats['date_from'] = min($stats['date_from'] ?? $day, $day);
                $stats['date_to'] = max($stats['date_to'] ?? $day, $day);
                $identity = $day.'|'.$this->normalize($title);
                if (isset($blockedIdentities[$identity])) {
                    $stats['ambiguous']++;
                    $this->example($stats, $title, $day, 'Needs review — same title/date has different exported metrics');
                    continue;
                }
                if (isset($seen[$identity])) { $stats['already_complete']++; continue; }
                $seen[$identity] = true;
                $metrics = $this->metrics($row);
                if ($metrics === []) { $stats['blank_metrics']++; continue; }

                [$show, $matchType, $ambiguous] = $this->matchShow($channelId, $date, $title);
                if ($ambiguous) {
                    $stats['ambiguous']++;
                    $this->example($stats, $title, $day, 'Needs review — duplicate title/date or different channel');
                    continue;
                }
                if ($show) {
                    $stats[$matchType.'_matched']++;
                    // The export rounds money to whole dollars. Keep precise existing
                    // cents when the exported number is the same rounded amount.
                    foreach (['gross_revenue', 'whatnot_net', 'avg_order_value', 'giveaway_spend'] as $field) {
                        if (isset($metrics[$field]) && $show->{$field} !== null && floor($metrics[$field]) === $metrics[$field]
                            && abs((float) $show->{$field} - $metrics[$field]) < 0.5) {
                            $metrics[$field] = (float) $show->{$field};
                        }
                    }
                    if (($metrics['show_duration'] ?? null) === 0 && (int) $show->show_duration > 0) {
                        unset($metrics['show_duration']);
                    }
                }
                $metadata = ['orders_count' => $this->number($row['Orders'] ?? null), 'thumbnail_impressions' => $this->number($row['Thumbnail Impressions'] ?? null),
                    'thumbnail_ctr' => $this->number($row['Thumbnail CTR'] ?? null, true), 'unique_viewers' => $this->number($row['Unique Viewers'] ?? null)];
                $metadata = array_filter($metadata, fn ($value) => $value !== null);
                $status = $this->complete(array_merge($show?->only(['gross_revenue', 'whatnot_net', 'show_duration', 'completed_earnings']) ?? [], $metrics)) ? 'complete' : 'partial';
                $payload = $show?->raw_import_payload ?? [];
                $payload = is_array($payload) ? $payload : [];
                $oldMeta = $payload['_analytics_csv_metrics'] ?? [];
                $changed = ! $show || collect($metrics)->contains(fn ($value, $key) => $this->different($show->getAttribute($key), $value))
                    || collect($metadata)->contains(fn ($value, $key) => $this->different($oldMeta[$key] ?? null, $value))
                    || $show->analytics_sync_status !== $status;
                if (! $changed) { $stats['already_complete']++; continue; }
                $creating = ! $show;
                $stats[$creating ? 'created' : 'updated']++;
                $this->example($stats, $title, $day, $creating ? 'Create historical show' : 'Update matched show');

                if (! $dryRun) {
                    DB::transaction(function () use ($show, $channelId, $title, $date, $metrics, $metadata, $row, $status, $payload) {
                        $record = $show ? Show::query()->lockForUpdate()->findOrFail($show->id) : new Show([
                            'whatnot_channel_id' => $channelId, 'title' => $title, 'show_date' => $date->toDateString(),
                            'status' => 'draft', 'import_source' => 'auto_whatnot', 'created_by' => auth()->id() ?? 1,
                        ]);
                        $before = $record->only(array_keys($metrics));
                        $raw = $record->raw_import_payload;
                        $raw = is_array($raw) ? $raw : $payload;
                        $raw['_analytics_csv_metrics'] = array_merge($raw['_analytics_csv_metrics'] ?? [], $metadata);
                        $raw['_analytics_csv'] = $row;
                        $raw['_analytics_csv_imported_at'] = now()->toIso8601String();
                        $record->forceFill(array_merge($metrics, [
                            'raw_import_payload' => $raw, 'analytics_sync_status' => $status,
                            'analytics_sync_note' => 'Imported from Whatnot Shows CSV. Orders are stored separately from units sold.'.(($metrics['show_duration'] ?? null) === 0 ? ' Zero duration requires a later analytics check.' : ''),
                            'last_analytics_synced_at' => now(), 'analytics_unavailable_at' => null,
                        ]))->save();
                        $changes = [];
                        foreach ($metrics as $key => $after) if ($this->different($before[$key] ?? null, $after)) $changes[$key] = ['before' => $before[$key] ?? null, 'after' => $after];
                        ShowIngestionLog::create([
                            'show_id' => $record->id, 'whatnot_channel_id' => $channelId, 'source' => 'whatnot_analytics_csv', 'status' => 'success',
                            'raw_payload' => ['event' => $show ? 'csv_analytics_import' : 'csv_historical_show_created', 'changed_count' => count($changes), 'changed_fields' => $changes, 'csv' => $row],
                        ]);
                    });
                }
            }
        } finally { fclose($handle); }
        return $stats;
    }


    /** Conflicting repeated title/date keys cannot be resolved without show UUIDs. */
    public function conflicts(array $paths): array
    {
        $identities = [];
        foreach ($paths as $path) {
            $file = fopen($path, 'rb');
            if (! $file) throw new RuntimeException('Could not open a CSV for matching.');
            try {
                if (fread($file, 3) !== "\xEF\xBB\xBF") rewind($file);
                $headers = fgetcsv($file, 0, ',', '"', '') ?: [];
                if (array_diff(self::REQUIRED_HEADERS, $headers)) throw new RuntimeException('Expected a Whatnot Shows CSV with Show and Date columns.');
                while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
                    if (count($values) !== count($headers)) continue;
                    $row = array_combine($headers, $values);
                    try { $date = Carbon::createFromFormat('!m/d/Y', trim($row['Date']))->toDateString(); }
                    catch (\Throwable) { continue; }
                    $metrics = $this->metrics($row);
                    if ($metrics === []) continue;
                    $key = $date.'|'.$this->normalize($row['Show']);
                    $signature = hash('sha256', json_encode([$metrics, $this->number($row['Orders'] ?? null), $this->number($row['Unique Viewers'] ?? null)]));
                    $identities[$key][$signature] = true;
                }
            } finally { fclose($file); }
        }
        return array_keys(array_filter($identities, fn ($variants) => count($variants) > 1));
    }

    private function matchShow(int $channelId, Carbon $date, string $title): array
    {
        $needle = $this->normalize($title);
        $pool = Show::whereBetween('show_date', [$date->copy()->subDay()->toDateString(), $date->copy()->addDay()->toDateString()])
            ->get()->filter(fn (Show $show) => $this->normalize($show->title) === $needle);
        $sameDay = $pool->filter(fn (Show $show) => $show->show_date?->isSameDay($date))->values();
        $matches = $sameDay->isNotEmpty() ? $sameDay : $pool->values();
        // The CSV has no UUID or channel. Never fuzzy-match or create a duplicate
        // when the same title/date is already attributed to another channel.
        if ($matches->count() > 1 || ($matches->count() === 1 && (int) $matches->first()->whatnot_channel_id !== $channelId)) return [null, 'exact', true];
        return [$matches->first(), $sameDay->isNotEmpty() ? 'exact' : 'date_tolerant', false];
    }

    private function metrics(array $row): array
    {
        $map = [
            ['gross_revenue', ['Est. Sales', 'Estimated Sales']],
            ['whatnot_net', ['Est. Earnings', 'Est. Earning', 'Estimated Earnings']],
            ['avg_order_value', ['AOV', 'Average Order Value']],
            ['giveaway_spend', ['Giveaway Spend']], ['giveaways_count', ['Giveaways']],
            ['buyers_count', ['Buyers']], ['first_time_buyers', ['First Time Buyers', 'First-Time Buyers']],
            ['returning_buyers', ['Returning Buyers']], ['shares_count', ['Show Shares', 'Shares']],
            ['show_duration', ['Show Duration (mins)', 'Show Duration', 'Duration (mins)']],
            ['max_concurrent_viewers', ['Max Concurrent Viewers']], ['total_views', ['Total Views']],
            ['avg_order_rating', ['Average Order Rating', 'Avg. Order Rating']],
        ];
        $out = [];
        foreach ($map as [$field, $columns]) foreach ($columns as $column) {
            if (! array_key_exists($column, $row)) continue;
            $value = $this->number($row[$column]);
            if ($value !== null) $out[$field] = in_array($field, ['gross_revenue', 'whatnot_net', 'avg_order_value', 'giveaway_spend', 'avg_order_rating'], true) ? $value : (int) round($value);
            break;
        }
        return $out;
    }

    private function number(mixed $raw, bool $percent = false): ?float
    {
        $value = str_replace([',', '$', '%'], '', trim((string) ($raw ?? '')));
        return is_numeric($value) ? (float) $value / ($percent ? 100 : 1) : null;
    }

    private function complete(array $metrics): bool
    {
        return isset($metrics['gross_revenue'], $metrics['whatnot_net'], $metrics['show_duration']) && $metrics['show_duration'] > 0
            && ($metrics['gross_revenue'] <= 0 || (float) ($metrics['completed_earnings'] ?? 0) > 0);
    }

    private function example(array &$stats, string $title, string $date, string $action): void
    {
        if (count($stats['examples']) < 20) $stats['examples'][] = compact('title', 'date', 'action');
    }

    private function normalize(?string $value): string
    {
        return (string) Str::of((string) $value)->lower()->squish()->replaceMatches('/[^\\pL\\pN]+/u', '');
    }

    private function different(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) return abs((float) $a - (float) $b) > 0.0001;
        return (string) ($a ?? '') !== (string) ($b ?? '');
    }
}
