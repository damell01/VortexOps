<?php

namespace App\Livewire;

use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\TimeEntry;
use App\Support\BusinessTime;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A streamer assistant's dashboard.
 *
 * They fell through to the generic dashboard, which offered receiving
 * shortcuts and nothing else. What an assistant actually does: clock in for
 * a shift, follow the shows of the streamers they support, look stock up for
 * them, and see what they have been paid. Read-only apart from the clock.
 */
class AssistantHome extends Component
{
    /** The streamers this assistant supports. */
    #[Computed]
    public function streamers(): Collection
    {
        return auth()->user()->assistedStreamers()->orderBy('name')->get(['streamers.id', 'streamers.name', 'streamers.business_id', 'streamers.user_id']);
    }

    private function showsQuery()
    {
        return Show::query()->inChannelContext()
            ->whereNotIn('status', ['cancelled'])
            ->whereHas('streamers', fn ($q) => $q->whereIn('streamers.id', $this->streamers->pluck('id')))
            ->with(['streamers:id,name', 'channel:id,name']);
    }

    #[Computed]
    public function upcoming(): Collection
    {
        if ($this->streamers->isEmpty()) return collect();

        return $this->showsQuery()
            ->where('show_date', '>=', BusinessTime::local()->toDateString())
            ->orderBy('show_date')->orderBy('start_time')
            ->limit(6)->get();
    }

    #[Computed]
    public function recent(): Collection
    {
        if ($this->streamers->isEmpty()) return collect();
        $today = BusinessTime::local();

        return $this->showsQuery()
            ->where('show_date', '<', $today->toDateString())
            ->where('show_date', '>=', $today->copy()->subDays(14)->toDateString())
            ->orderByDesc('show_date')->orderByDesc('start_time')
            ->limit(6)->get();
    }

    /** Hours this pay week, counted the way payroll counts them. */
    #[Computed]
    public function hours(): array
    {
        $start = BusinessTime::local()->startOfWeek();
        $entries = TimeEntry::query()->where('user_id', auth()->id())
            ->where('clocked_in_at', '>=', $start->copy()->utc())
            ->whereNotNull('clocked_out_at')->get();

        return [
            'week' => round($entries->sum(fn (TimeEntry $e) => $e->payableMinutes()) / 60, 2),
            'shifts' => $entries->count(),
            'review' => $entries->where('status', 'flagged')->count(),
            'from' => $start,
        ];
    }

    /** Pay that has been marked paid — the same rule streamers see. */
    #[Computed]
    public function pay(): array
    {
        $profile = auth()->user()->streamer;
        if (! $profile) return ['last' => [], 'year' => 0.0];

        $paid = Payout::query()->where('streamer_id', $profile->id)
            ->where(fn ($q) => $q->where('status', 'paid')->orWhereHas('batch', fn ($b) => $b->where('status', 'paid')))
            ->with('batch:id,week_start,week_end,status')
            ->get(['id', 'weekly_payout_batch_id', 'calculated_payout', 'created_at']);

        $runs = $paid->groupBy('weekly_payout_batch_id')->map(fn ($rows) => [
            'from' => $rows->first()->batch?->week_start,
            'to' => $rows->first()->batch?->week_end,
            'amount' => (float) $rows->sum('calculated_payout'),
        ])->sortByDesc(fn ($r) => $r['to']?->timestamp ?? 0)->values();

        $year = BusinessTime::local()->year;

        return [
            'last' => $runs->take(3)->all(),
            'year' => (float) $runs->filter(fn ($r) => $r['to']?->year === $year)->sum('amount'),
        ];
    }

    /** Stock where the supported streamers keep it. */
    #[Computed]
    public function inventory(): array
    {
        $ids = $this->streamers->flatMap(fn (Streamer $s) => $s->inventoryLocations()->pluck('id'))->unique()->values();
        $rows = InventoryStock::query()->whereIn('inventory_location_id', $ids)->where('quantity', '>', 0);

        return [
            'locations' => $ids->count(),
            'items' => (clone $rows)->distinct()->count('inventory_item_id'),
            'units' => (float) (clone $rows)->sum('quantity'),
        ];
    }

    public function render()
    {
        return view('livewire.assistant-home');
    }
}
