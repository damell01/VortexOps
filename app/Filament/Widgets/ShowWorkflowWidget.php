<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\EndOfStreamForm;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * A single, role-aware view of where shows are in the operating flow.
 *
 * The underlying records remain the source of truth; this widget only maps
 * those existing states into plain-language stages so admins, streamers and
 * fulfillment can tell what is waiting on whom without opening several pages.
 */
class ShowWorkflowWidget extends Widget
{
    protected static ?int $sort = 0;
    protected int|string|array $columnSpan = 'full';
    protected static bool $isLazy = true;
    protected string $view = 'filament.widgets.show-workflow';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && (
            $user->isAdmin()
            || $user->isOwner()
            || $user->isStreamer()
            || $user->isFulfillment()
            || $user->isFulfillmentAdmin()
        ));
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $mode = ($user?->isAdmin() || $user?->isOwner())
            ? 'admin'
            : (($user?->isFulfillment() || $user?->isFulfillmentAdmin()) ? 'fulfillment' : 'streamer');

        $query = Show::query()
            ->inChannelContext()
            ->whereNotIn('status', ['cancelled'])
            ->with([
                'streamers:id,name,member_type',
                'streamerLogEntry:id,show_id,streamer_id,status,submitted_at,fulfillment_reviewed_at,approval_status',
                'payouts:id,show_id,status,weekly_payout_batch_id,calculated_payout',
            ])
            ->withCount([
                'shipments as open_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ]);

        if ($mode === 'streamer') {
            $streamerId = $user?->streamer?->id;
            $query->when($streamerId, fn ($q) => $q->whereHas('streamers', fn ($s) => $s->where('streamers.id', $streamerId)))
                ->when(! $streamerId, fn ($q) => $q->whereRaw('1 = 0'));
        } elseif ($mode === 'fulfillment' && ! $user?->isFulfillmentAdmin()) {
            $query->whereHas('fulfillmentUsers', fn ($q) => $q->where('users.id', $user->id));
        }

        $shows = $query
            ->orderByRaw('CASE WHEN show_date >= ? THEN 0 ELSE 1 END', [today()->toDateString()])
            ->orderBy('show_date')
            ->limit(12)
            ->get();

        $rows = $shows->map(fn (Show $show) => $this->mapShow($show, $mode));

        $counts = collect([
            'streamer' => $rows->whereIn('stage_key', ['streamer_log', 'changes_requested'])->count(),
            'admin' => $rows->where('stage_key', 'admin_review')->count(),
            'fulfillment' => $rows->where('stage_key', 'fulfillment')->count(),
            'payroll' => $rows->where('stage_key', 'payroll_ready')->count(),
        ]);

        return compact('rows', 'counts', 'mode');
    }

    /** @return array<string,mixed> */
    private function mapShow(Show $show, string $mode): array
    {
        $log = $show->streamerLogEntry;
        $payouts = $show->payouts;
        $paid = $payouts->isNotEmpty() && $payouts->every(fn ($p) => $p->status === 'paid');
        $inPayRun = $payouts->contains(fn ($p) => ! empty($p->weekly_payout_batch_id));
        $ended = $show->show_date?->isPast() || $show->show_date?->isToday();

        if ($paid) {
            [$key, $label, $tone, $hint] = ['paid', 'Paid', 'success', 'Payroll complete'];
        } elseif ($inPayRun) {
            [$key, $label, $tone, $hint] = ['payroll_ready', 'In Pay Run', 'primary', 'Included in weekly payroll'];
        } elseif ($log?->status === 'admin_approved' && ($log->fulfillment_reviewed_at || (int) $show->open_shipments_count === 0)) {
            [$key, $label, $tone, $hint] = ['payroll_ready', 'Payroll Ready', 'success', 'Show is signed off'];
        } elseif ($log?->status === 'admin_approved') {
            [$key, $label, $tone, $hint] = ['fulfillment', 'Fulfillment', 'info', 'Shipping / fulfillment review'];
        } elseif ($log?->status === 'streamer_reviewed' || $log?->submitted_at) {
            [$key, $label, $tone, $hint] = ['admin_review', 'Admin Review', 'warning', 'Streamer report submitted'];
        } elseif ($log?->status === 'changes_requested') {
            [$key, $label, $tone, $hint] = ['changes_requested', 'Changes Requested', 'danger', 'Streamer needs to update report'];
        } elseif ($ended) {
            [$key, $label, $tone, $hint] = ['streamer_log', 'Streamer Logging', 'warning', 'End-of-stream report needed'];
        } else {
            [$key, $label, $tone, $hint] = ['upcoming', 'Upcoming', 'gray', 'Scheduled show'];
        }

        $url = $mode === 'streamer' && in_array($key, ['streamer_log', 'changes_requested', 'admin_review'], true)
            ? EndOfStreamForm::getUrl(['showId' => $show->id])
            : ShowResource::getUrl('view', ['record' => $show]);

        return [
            'id' => $show->id,
            'title' => $show->title ?: "Show #{$show->id}",
            'date' => $show->show_date,
            'streamers' => $show->streamers->pluck('name')->filter()->join(', '),
            'gross' => (float) ($show->gross_revenue ?? 0),
            'open_shipments' => (int) $show->open_shipments_count,
            'payout_total' => (float) $payouts->sum('calculated_payout'),
            'stage_key' => $key,
            'stage' => $label,
            'tone' => $tone,
            'hint' => $hint,
            'url' => $url,
        ];
    }
}
