<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\DB;

/**
 * Whatnot net earned on the shows a business's streamers hosted, per channel,
 * for a period: "how much did VCX's streamers make on VortexBreaks and Vortex
 * Cards this pay period". A show belongs to the business of its primary
 * streamer, the same rule as the P&L and business statements. Read-only.
 */
final class BusinessChannelNet
{
    /**
     * @return array{business: ?string, total: float, shows: int, channels: list<array{channel: string, net: float, shows: int}>}
     */
    public function between(?int $businessId, string $from, string $until): array
    {
        $business = $businessId ? Business::query()->find($businessId) : null;
        if (! $business) {
            return ['business' => null, 'total' => 0.0, 'shows' => 0, 'channels' => [], 'pair' => ['net' => 0.0, 'shows' => 0]];
        }

        $rows = DB::table('shows')
            ->leftJoin('whatnot_channels', 'whatnot_channels.id', '=', 'shows.whatnot_channel_id')
            // Half-open by day, so a date stored with a time still counts.
            ->where('shows.show_date', '>=', $from)
            ->where('shows.show_date', '<', \Illuminate\Support\Carbon::parse($until)->addDay()->toDateString())
            ->where('shows.status', '!=', 'cancelled')
            ->whereExists(fn ($q) => $q->from('show_streamer')
                ->join('streamers', 'streamers.id', '=', 'show_streamer.streamer_id')
                ->whereColumn('show_streamer.show_id', 'shows.id')
                ->where('show_streamer.is_primary', true)
                ->where('streamers.business_id', $business->id))
            ->groupBy('shows.whatnot_channel_id', 'whatnot_channels.name')
            ->selectRaw('whatnot_channels.name as channel, COUNT(*) as shows, SUM(COALESCE(shows.whatnot_net, 0)) as net')
            ->get();

        $channels = $rows->map(fn ($r) => ['channel' => $r->channel ?: 'No channel', 'net' => round((float) $r->net, 2), 'shows' => (int) $r->shows])
            ->sortByDesc('net')->values()->all();

        return [
            'business' => $business->short_name ?: $business->name,
            'total' => round((float) $rows->sum('net'), 2),
            'shows' => (int) $rows->sum('shows'),
            'channels' => $channels,
            'pair' => self::pair($channels),
        ];
    }

    /** The two stream channels payroll checks together. */
    public const PAIR_LABEL = 'Vortex Breaks + Vortex Cards';

    /**
     * Vortex Breaks and Vortex Cards added together (Vortex Shop and any other
     * channel left out), matched by name whatever the spacing or case.
     *
     * @param  list<array{channel:string,net:float,shows:int}>  $channels
     * @return array{net: float, shows: int}
     */
    public static function pair(array $channels): array
    {
        $rows = array_filter($channels, fn ($c) => in_array(preg_replace('/[^a-z]/', '', strtolower($c['channel'])), ['vortexbreaks', 'vortexcards'], true));

        return ['net' => round(array_sum(array_column($rows, 'net')), 2), 'shows' => (int) array_sum(array_column($rows, 'shows'))];
    }

    /** The business named VCX (Vortex Collects), whose streamers' net payroll checks against. */
    public static function vcxId(): ?int
    {
        $id = Business::query()->where('short_name', 'VCX')->orWhere('name', 'Vortex Collects')->value('id');

        return $id ? (int) $id : null;
    }
}
