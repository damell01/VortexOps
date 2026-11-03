<?php

namespace App\Services;

use App\Models\Show;
use App\Models\ShowChangeLog;
use App\Models\StreamerLogEntry;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Bring Seller API shows (GET /shows, Show schema) into VortexOps.
 *
 * Matching, safest first — the same rules the scraper uses:
 *  1. Whatnot's show ID on a show, or inside its link.
 *  2. Otherwise exact title + date on the same channel, when exactly one
 *     show fits and it does not already carry a different Whatnot ID.
 * A match is given the Whatnot ID, which is what lets orders, analytics and
 * tips attach to it. No match → the show is created (as a draft).
 *
 * Every change is written to the show's change log with source whatnot_api,
 * so titles, times and figures can be reviewed. Scheduled shows follow
 * reschedules; an ended show's date is not moved by scheduled_start_at,
 * which Whatnot does not update when the show actually goes live.
 */
final class WhatnotApiShowImporter
{
    public const SOURCE = 'whatnot_api';

    /**
     * @param array<string,mixed> $api one Show object
     * @return array{outcome:string, show_id:?int}
     */
    public function import(int $channelId, array $api, bool $dryRun = true): array
    {
        if (! $dryRun && ! WhatnotApiOrderApplier::writesAllowed()) {
            throw new RuntimeException('Applying API data is disabled.');
        }
        $id = is_string($api['id'] ?? null) ? strtolower($api['id']) : null;
        $title = is_string($api['title'] ?? null) ? trim($api['title']) : null;
        if (! $id || ! preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/', $id) || ! $title) {
            return ['outcome' => 'invalid', 'show_id' => null];
        }
        $status = (string) ($api['status'] ?? '');
        $when = $this->localTime($api['scheduled_start_at'] ?? null);
        $ended = $this->localTime($api['ended_at'] ?? null);

        $show = $this->match($channelId, $id, $title, $when?->toDateString());
        if ($show === false) {
            return ['outcome' => 'other_channel', 'show_id' => null];
        }

        if ($show === null) {
            if ($status === 'cancelled' || ! $when) {
                return ['outcome' => $status === 'cancelled' ? 'cancelled_not_created' : 'no_date', 'show_id' => null];
            }
            if ($dryRun) {
                return ['outcome' => 'created', 'show_id' => null];
            }
            $twin = Show::query()->where('title', $title)->whereDate('show_date', $when->toDateString())
                ->whereNotNull('whatnot_channel_id')->where('whatnot_channel_id', '!=', $channelId)->exists();
            $show = Show::create(array_filter([
                'whatnot_channel_id' => $channelId,
                'whatnot_show_id' => $id,
                'title' => $title,
                'show_date' => $when->toDateString(),
                'start_time' => $when->format('H:i:s'),
                'end_time' => $ended?->format('H:i:s'),
                'detail_url' => is_string($api['url'] ?? null) ? $api['url'] : null,
                'status' => 'draft',
                'import_source' => 'auto_whatnot',
                'raw_import_payload' => ['_source' => 'seller_api', 'show' => $api],
                'channel_attribution_suspect' => $twin ?: null,
            ], fn ($v) => $v !== null));
            ShowChangeLog::create(['show_id' => $show->id, 'field_name' => 'created', 'old_value' => null,
                'new_value' => $title, 'changed_by' => 'system', 'source' => self::SOURCE, 'notes' => 'Created from the Whatnot Seller API ('.$status.')']);
            $show->detectStreamers();
            $show->loadMissing('streamers');
            if ($streamer = $show->primaryStreamer()) {
                StreamerLogEntry::firstOrCreate(['show_id' => $show->id], ['streamer_id' => $streamer->id, 'status' => 'pending', 'gross_revenue' => $show->gross_revenue]);
            }
            return ['outcome' => 'created', 'show_id' => $show->id];
        }

        $changes = [];
        if (blank($show->whatnot_show_id)) $changes['whatnot_show_id'] = $id;
        if ($show->whatnot_channel_id === null) $changes['whatnot_channel_id'] = $channelId;
        if ($show->title !== $title) $changes['title'] = $title;
        if (blank($show->detail_url) && is_string($api['url'] ?? null)) $changes['detail_url'] = $api['url'];
        if ($when && ($status === 'scheduled' || $show->start_time === null)) {
            if ($status === 'scheduled' && $show->show_date?->toDateString() !== $when->toDateString()) $changes['show_date'] = $when->toDateString();
            if ($show->start_time === null || ($status === 'scheduled' && substr((string) $show->start_time, 0, 5) !== $when->format('H:i'))) {
                $changes['start_time'] = $when->format('H:i:s');
            }
        }
        if ($ended && $show->end_time === null) $changes['end_time'] = $ended->format('H:i:s');
        if ($status === 'cancelled' && $show->status === 'draft') $changes['status'] = 'cancelled';

        if ($changes === []) {
            return ['outcome' => 'unchanged', 'show_id' => $show->id];
        }
        if (! $dryRun) {
            self::logChanges($show, $changes, isset($changes['whatnot_show_id']) ? 'Linked to Whatnot show '.$id : null);
            $show->forceFill($changes)->save();
        }

        return ['outcome' => isset($changes['whatnot_show_id']) ? 'linked' : 'updated', 'show_id' => $show->id];
    }

    /**
     * Record each changed field in the show's change log (source whatnot_api).
     *
     * @param array<string,mixed> $changes field => new value
     */
    public static function logChanges(Show $show, array $changes, ?string $notes = null): void
    {
        foreach ($changes as $field => $new) {
            $old = $show->getRawOriginal($field) ?? $show->{$field};
            if (is_numeric($old) && is_numeric($new) && abs((float) $old - (float) $new) < 0.0001) continue;
            if ((string) $old === (string) (is_bool($new) ? (int) $new : $new)) continue;
            ShowChangeLog::create([
                'show_id' => $show->id,
                'field_name' => $field,
                'old_value' => is_scalar($old) || $old === null ? $old : json_encode($old),
                'new_value' => is_scalar($new) || $new === null ? (is_bool($new) ? (int) $new : $new) : json_encode($new),
                'changed_by' => auth()->user()?->email ?? 'system',
                'source' => self::SOURCE,
                'notes' => $notes,
            ]);
        }
    }

    /** @return Show|null|false false = the ID belongs to another channel's show */
    private function match(int $channelId, string $id, string $title, ?string $date): Show|null|false
    {
        $byId = Show::query()->where(fn ($q) => $q->whereRaw('LOWER(whatnot_show_id) = ?', [$id])->orWhere('detail_url', 'like', '%'.$id.'%'))
            ->orderBy('id')->get();
        if ($byId->isNotEmpty()) {
            $own = $byId->first(fn (Show $s) => $s->whatnot_channel_id === null || (int) $s->whatnot_channel_id === $channelId);
            return $own ?? false;
        }
        if (! $date) {
            return null;
        }
        $candidates = Show::query()
            ->where(fn ($q) => $q->where('whatnot_channel_id', $channelId)->orWhereNull('whatnot_channel_id'))
            ->where('title', $title)->whereDate('show_date', $date)
            ->where(fn ($q) => $q->whereNull('whatnot_show_id')->orWhere('whatnot_show_id', ''))
            ->limit(2)->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function localTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') return null;
        try {
            return Carbon::parse($value)->setTimezone((string) config('vortex.whatnot.timezone', 'America/Chicago'));
        } catch (\Throwable) {
            return null;
        }
    }
}
