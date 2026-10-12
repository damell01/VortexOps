<?php

namespace App\Services;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Suggests the channel a flagged show actually ran on, for Channel Review.
 *
 * Plain rules over history, no AI model, so it is instant and explainable:
 *
 *  1. The title names a channel ("w/Dennis_VortexCollects") — strongest.
 *  2. The show's streamers' confirmed shows mostly ran on one channel.
 *  3. Confirmed shows in the same weekday + start hour mostly ran on one channel.
 *  4. The ingestion logs record which channels the show was imported under;
 *     a suggestion outside them is downgraded one level.
 *
 * "Confirmed" means a show with a channel and no attribution flag. History is
 * read once per instance and memoized, so a page of cards or a bulk run over
 * the whole queue costs a handful of queries.
 */
final class ChannelSuggester
{
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';

    /** Minimum confirmed shows before streamer or slot history counts. */
    private const MIN_HISTORY = 3;

    /** @var array<int, array{name: string, needles: list<string>}>|null */
    private ?array $channels = null;

    /** @var array<int, array<int, int>>|null streamer_id => [channel_id => shows] */
    private ?array $byStreamer = null;

    /** @var array<string, array<int, int>>|null "weekday-hour" => [channel_id => shows] */
    private ?array $bySlot = null;

    /** @var array<int, list<int>> */
    private array $importedUnder = [];

    /**
     * Channels this show's imports ran under, assigned channel first.
     *
     * @return list<int>
     */
    public function importedUnder(Show $show): array
    {
        if (! array_key_exists($show->id, $this->importedUnder)) {
            $this->primeImportedUnder([$show]);
        }

        return $this->importedUnder[$show->id];
    }

    /**
     * One query for the ingestion-log channels of many shows.
     *
     * @param  iterable<Show>  $shows
     */
    public function primeImportedUnder(iterable $shows): void
    {
        $shows = collect($shows)->reject(fn (Show $s) => array_key_exists($s->id, $this->importedUnder));
        if ($shows->isEmpty()) {
            return;
        }

        $logged = ShowIngestionLog::query()
            ->whereIn('show_id', $shows->pluck('id'))->whereNotNull('whatnot_channel_id')
            ->distinct()->get(['show_id', 'whatnot_channel_id'])
            ->groupBy('show_id');

        foreach ($shows as $show) {
            $this->importedUnder[$show->id] = collect($logged->get($show->id, []))
                ->map(fn ($row) => (int) $row->whatnot_channel_id)
                ->prepend((int) $show->whatnot_channel_id)
                ->filter()->unique()->values()->all();
        }
    }

    public function channelName(int $id): string
    {
        return $this->channels()[$id]['name'] ?? "Channel #{$id}";
    }

    /**
     * @return array{channel_id: int, name: string, level: string, reasons: list<string>}|null
     */
    public function suggest(Show $show): ?array
    {
        $channels = $this->channels();
        $reasons = [];

        // 1. Title names a channel.
        $title = $this->squash($show->title);
        $titleHits = [];
        foreach ($channels as $id => $channel) {
            foreach ($channel['needles'] as $needle) {
                if ($needle !== '' && str_contains($title, $needle)) {
                    $titleHits[] = $id;
                    break;
                }
            }
        }

        // 2. Streamer history.
        $streamerVotes = [];
        foreach ($show->streamers as $streamer) {
            foreach ($this->byStreamer()[$streamer->id] ?? [] as $channelId => $count) {
                $streamerVotes[$channelId] = ($streamerVotes[$channelId] ?? 0) + $count;
            }
        }
        [$streamerTop, $streamerShare, $streamerTotal] = $this->top($streamerVotes);
        $streamerNames = $show->streamers->pluck('name')->implode(' & ');

        // 3. Weekday + start hour.
        $slot = $this->slotKey($show);
        [$slotTop, $slotShare, $slotTotal] = $this->top($slot ? ($this->bySlot()[$slot] ?? []) : []);

        if (count($titleHits) === 1) {
            $choice = $titleHits[0];
            $reasons[] = 'Title names '.$this->channelName($choice).'.';
            $agrees = $streamerTotal < self::MIN_HISTORY || $streamerTop === $choice
                || ($streamerVotes[$choice] ?? 0) / max(1, $streamerTotal) >= 0.3;
            $level = $agrees ? self::HIGH : self::MEDIUM;
            if ($streamerTotal >= self::MIN_HISTORY) {
                $reasons[] = $this->streamerReason($streamerNames, $streamerVotes[$choice] ?? 0, $streamerTotal, $choice);
            }
        } elseif ($streamerTotal >= self::MIN_HISTORY) {
            $choice = $streamerTop;
            $reasons[] = $this->streamerReason($streamerNames, $streamerVotes[$choice], $streamerTotal, $choice);
            $slotAgrees = $slotTotal < self::MIN_HISTORY || $slotTop === $choice;
            $level = $streamerShare >= 0.9 && $streamerTotal >= 5 && $slotAgrees ? self::HIGH
                : ($streamerShare >= 0.7 ? self::MEDIUM : self::LOW);
            if ($slotTotal >= self::MIN_HISTORY) {
                $reasons[] = $this->slotReason($show, $this->bySlot()[$slot][$choice] ?? 0, $slotTotal, $choice);
            }
        } elseif ($slotTotal >= self::MIN_HISTORY) {
            // A time slot alone is a hint, never enough to confirm in bulk.
            $choice = $slotTop;
            $level = $slotShare >= 0.8 ? self::MEDIUM : self::LOW;
            $reasons[] = $this->slotReason($show, (int) round($slotShare * $slotTotal), $slotTotal, $choice);
        } else {
            return null;
        }

        // 4. Import evidence.
        $imported = $this->importedUnder($show);
        if (count($imported) > 1 && ! in_array($choice, $imported, true)) {
            $level = $level === self::HIGH ? self::MEDIUM : self::LOW;
            $reasons[] = 'It was not imported under '.$this->channelName($choice).', so double-check.';
        }

        return ['channel_id' => $choice, 'name' => $this->channelName($choice), 'level' => $level, 'reasons' => $reasons];
    }

    /** @return array<int, array{name: string, needles: list<string>}> */
    private function channels(): array
    {
        return $this->channels ??= WhatnotChannel::query()->get(['id', 'name', 'whatnot_username', 'display_title'])
            ->mapWithKeys(fn (WhatnotChannel $c) => [$c->id => [
                'name' => $c->name,
                // Whole names only: single words like "breaks" or "cards"
                // appear in titles that have nothing to do with the channel.
                'needles' => collect([$c->name, $c->whatnot_username, $c->display_title])
                    ->map(fn ($v) => $this->squash($v))->filter(fn ($v) => strlen($v) >= 6)->unique()->values()->all(),
            ]])->all();
    }

    /** @return array<int, array<int, int>> */
    private function byStreamer(): array
    {
        if ($this->byStreamer !== null) {
            return $this->byStreamer;
        }

        $this->byStreamer = [];
        DB::table('show_streamer')
            ->join('shows', 'shows.id', '=', 'show_streamer.show_id')
            ->where('shows.channel_attribution_suspect', false)->whereNotNull('shows.whatnot_channel_id')
            ->groupBy('show_streamer.streamer_id', 'shows.whatnot_channel_id')
            ->selectRaw('show_streamer.streamer_id as streamer_id, shows.whatnot_channel_id as channel_id, count(*) as shows')
            ->get()
            ->each(function ($row) {
                $this->byStreamer[(int) $row->streamer_id][(int) $row->channel_id] = (int) $row->shows;
            });

        return $this->byStreamer;
    }

    /** @return array<string, array<int, int>> */
    private function bySlot(): array
    {
        if ($this->bySlot !== null) {
            return $this->bySlot;
        }

        $this->bySlot = [];
        Show::query()
            ->where('channel_attribution_suspect', false)->whereNotNull('whatnot_channel_id')->whereNotNull('start_time')
            ->whereDate('show_date', '>=', now()->subYear())
            ->get(['id', 'show_date', 'start_time', 'whatnot_channel_id'])
            ->each(function (Show $show) {
                if ($key = $this->slotKey($show)) {
                    $this->bySlot[$key][(int) $show->whatnot_channel_id] = ($this->bySlot[$key][(int) $show->whatnot_channel_id] ?? 0) + 1;
                }
            });

        return $this->bySlot;
    }

    private function slotKey(Show $show): ?string
    {
        if (! $show->start_time) {
            return null;
        }

        $start = $show->start_time->copy()->timezone(config('app.timezone'));

        return $start->dayOfWeek.'-'.$start->hour;
    }

    /**
     * @param  array<int, int>  $votes
     * @return array{0: int|null, 1: float, 2: int} top channel, its share, total
     */
    private function top(array $votes): array
    {
        $total = array_sum($votes);
        if ($total === 0) {
            return [null, 0.0, 0];
        }
        arsort($votes);
        $top = array_key_first($votes);

        return [$top, $votes[$top] / $total, $total];
    }

    private function streamerReason(string $names, int $count, int $total, int $channelId): string
    {
        return ($names ?: 'These streamers').": {$count} of {$total} confirmed shows on ".$this->channelName($channelId).'.';
    }

    private function slotReason(Show $show, int $count, int $total, int $channelId): string
    {
        $start = $show->start_time->copy()->timezone(config('app.timezone'));

        return $start->format('l g A').": {$count} of {$total} confirmed shows on ".$this->channelName($channelId).'.';
    }

    private function squash(?string $value): string
    {
        return (string) Str::of((string) $value)->lower()->replaceMatches('/[^a-z0-9]+/', '');
    }
}
