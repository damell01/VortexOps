<?php

namespace App\Services;

use App\Models\Show;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\ProfitShareFormula;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Shared competition figures only: never load costs, payouts, or private report data. */
class StreamerShowLeaderboard
{
    public function rows(User $viewer, Carbon $from, Carbon $to, ?int $channel = null): Collection
    {
        if (! $viewer->isAdmin() && ! $viewer->isStreamer()) return collect();
        if (! $viewer->isAdmin() && ! $viewer->streamer) return collect();
        $now = BusinessTime::local();
        $shows = Show::query()->where('is_operational', true)->where('status', '!=', 'cancelled')->whereDate('show_date', '<=', $now->toDateString())
            ->whereBetween('show_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->when($channel, fn ($q) => $q->where('whatnot_channel_id', $channel))
            ->has('streamers')
            ->with(['channel:id,name', 'streamers:id,name,user_id', 'streamers.user:id,buddy'])
            ->select(['id', 'title', 'show_date', 'start_time', 'end_time', 'show_duration', 'whatnot_channel_id', 'whatnot_net', 'completed_earnings', 'analytics_sync_status'])
            ->addSelect('raw_import_payload->_seller_hub_state as seller_state')
            ->withCount('streamers')->get()
            ->filter(function ($show) use ($now) {
                $start = $show->startsAt(BusinessTime::zone());
                if ($start?->gt($now)) return false;
                [$a, $b] = $show->scheduleMinutes();
                if ($start && $b !== null && $start->copy()->addMinutes($b - $a)->gt($now)) return false;
                // Today's results need an ended schedule or Seller Hub's Past marker.
                if ($show->show_date->toDateString() === $now->toDateString() && $show->seller_state !== 'past') {
                    if ($start === null || $b === null) return false;
                }
                return ProfitShareFormula::earningsForShow($show) !== null;
            });
        $rows = collect();
        foreach ($shows as $show) {
            $earnings = ProfitShareFormula::earningsForShow($show);
            $hours = $show->show_duration > 0 ? (float) $show->show_duration / 60 : null;
            $primary = $show->streamers->firstWhere('pivot.is_primary', true) ?? $show->streamers->first();
            $hosts = collect([$primary]);
            foreach ($hosts as $host) {
                $value = $earnings;
                if ($value === null || ! $host) continue;
                $buddy = $host->user?->buddy;
                $buddy = is_string($buddy) && preg_match('/^[a-z0-9-]+$/', $buddy) && is_file(public_path('img/pokemon3d/'.$buddy.'.gif')) ? $buddy : ['pikachu', 'charizard', 'blastoise', 'gengar', 'dragonite', 'eevee', 'snorlax', 'lucario'][$host->id % 8];
                $rows->push([
                    'show_id' => $show->id, 'streamer_id' => $host->id, 'name' => $host->name,
                    'title' => $show->title ?: 'Show #'.$show->id, 'date' => $show->show_date->format('M j'),
                    'channel' => $show->channel?->name ?? 'No channel', 'hosts' => $show->streamers->pluck('name')->all(),
                    'cohosted' => $show->streamers_count > 1,
                    'hours' => $hours, 'value' => $value, 'buddy' => $buddy,
                    'effect' => match ($buddy) {
                        'pikachu', 'raichu', 'pichu', 'jolteon', 'zapdos', 'ampharos', 'luxray' => 'electric',
                        'charmander', 'charmeleon', 'charizard', 'flareon', 'arcanine', 'vulpix', 'ninetales', 'moltres' => 'fire',
                        'squirtle', 'wartortle', 'blastoise', 'vaporeon', 'gyarados', 'lapras', 'mudkip', 'piplup' => 'water',
                        'bulbasaur', 'ivysaur', 'venusaur', 'leafeon', 'chikorita', 'sprigatito', 'petilil' => 'leaf',
                        'gengar', 'gastly', 'haunter', 'mew', 'mewtwo', 'espeon', 'abra', 'alakazam', 'lucario' => 'aura',
                        default => 'stars',
                    },
                    'mine' => $viewer->streamer?->id === $host->id || $show->streamers->contains('id', $viewer->streamer?->id),
                    'estimated' => $show->completed_earnings === null,
                ]);
            }
        }
        $rows = $rows->sort(fn ($a, $b) => ($b['value'] <=> $a['value']) ?: ($a['show_id'] <=> $b['show_id']) ?: ($a['streamer_id'] <=> $b['streamer_id']));
        $previous = null;
        $rank = 0;
        return $rows->values()->map(function ($row, $i) use (&$previous, &$rank) {
            if ($previous === null || $row['value'] !== $previous) $rank = $i + 1;
            $previous = $row['value'];
            return $row + ['rank' => $rank];
        });
    }
}
