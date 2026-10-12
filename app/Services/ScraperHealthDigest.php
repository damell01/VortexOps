<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use App\Support\ScraperStatus;
use Illuminate\Support\Carbon;

/**
 * The weekly "is the Whatnot import healthy?" digest.
 *
 * What came in over the last 7 days, which pipelines are not OK, where the
 * data has gaps, and how long the saved Whatnot sign-in has left. The
 * scraper cannot sign itself in (Cloudflare challenges an automated browser),
 * so an expiring session is the one thing worth warning about weeks ahead.
 */
class ScraperHealthDigest
{
    /** Warn when the saved sign-in has fewer days left than this. */
    public const SESSION_WARN_DAYS = 14;

    /** @return array<string, mixed> */
    public function build(?Carbon $since = null): array
    {
        $since ??= now()->subDays(7);

        $failures = ShowIngestionLog::query()
            ->whereIn('status', ['failed', 'partial'])->where('created_at', '>=', $since)
            ->latest('created_at')->get(['source', 'error_message', 'created_at']);

        $pipelines = collect(ScraperStatus::jobs())
            ->reject(fn (array $job) => $job['state'] === 'ok')
            ->map(fn (array $job) => ['label' => $job['label'], 'state' => $job['state'], 'note' => $job['note']])
            ->values()->all();

        $digest = [
            'period' => $since->format('M j').' – '.now()->format('M j, Y'),
            'imported' => [
                'new_shows' => Show::where('created_at', '>=', $since)->count(),
                'refreshed_shows' => Show::where('last_synced_at', '>=', $since)->count(),
                'orders' => WhatnotShowOrder::where('created_at', '>=', $since)->count(),
                'shipments_new' => Shipment::where('created_at', '>=', $since)->count(),
                'shipments_updated' => Shipment::where('updated_at', '>=', $since)->count(),
            ],
            'failures' => [
                'count' => $failures->count(),
                'by_source' => $failures->groupBy('source')->map(fn ($group, $source) => [
                    'source' => $source,
                    'count' => $group->count(),
                    'last_error' => \Illuminate\Support\Str::limit((string) strtok((string) $group->first()->error_message, "\n"), 160),
                ])->sortByDesc('count')->values()->all(),
            ],
            'pipelines' => $pipelines,
            'gaps' => $this->gaps($since),
            'session' => $this->session(),
        ];

        $digest['needs_attention'] = $digest['failures']['count'] > 0
            || $pipelines !== []
            || $digest['gaps']['shows_without_orders'] > 0
            || $digest['gaps']['shows_without_figures'] > 0
            || in_array($digest['session']['state'], ['missing', 'expired', 'expiring'], true);

        return $digest;
    }

    /** Shows that ran but are missing data, and channels that went quiet. */
    private function gaps(Carbon $since): array
    {
        // A day's grace: orders and analytics land a few hours after a show.
        $ran = Show::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('show_date', [$since->toDateString(), now()->subDay()->endOfDay()->toDateTimeString()]);

        $noOrders = (clone $ran)->where('units_sold', '>', 0)->whereDoesntHave('orders')
            ->orderByDesc('show_date')->get(['id', 'title', 'show_date']);

        $noFigures = (clone $ran)->where(fn ($q) => $q->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0))->count();

        $quietChannels = WhatnotChannel::query()
            ->where('status', 'active')->where('include_in_import', true)
            ->whereDoesntHave('shows', fn ($q) => $q->where('show_date', '>=', $since->toDateString()))
            ->orderBy('name')->pluck('name')->all();

        return [
            'shows_without_orders' => $noOrders->count(),
            'shows_without_orders_examples' => $noOrders->take(5)->map(fn (Show $s) => $s->show_date?->format('M j').' — '.$s->title)->all(),
            'shows_without_figures' => $noFigures,
            'quiet_channels' => $quietChannels,
        ];
    }

    /** @return array{state: string, saved_at: ?Carbon, expires_at: ?Carbon, days_left: ?int} */
    private function session(): array
    {
        $session = ScraperStatus::session();
        $expires = $session['expires_at'] ?? null;
        $daysLeft = $expires ? (int) floor(now()->diffInDays($expires, false)) : null;

        $state = match (true) {
            ! $session['exists'] => 'missing',
            $expires === null => 'unknown',
            $expires->isPast() => 'expired',
            $daysLeft < self::SESSION_WARN_DAYS => 'expiring',
            default => 'ok',
        };

        return ['state' => $state, 'saved_at' => $session['saved_at'], 'expires_at' => $expires, 'days_left' => $daysLeft];
    }
}
