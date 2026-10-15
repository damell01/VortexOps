<?php

namespace App\Livewire;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\BusinessTime;
use App\Support\ShowReportingGoLive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A streamer's home page: their next show, their numbers for a period, the
 * time clock, and every show report as a card — to complete first.
 */
class StreamerHome extends Component
{
    use WithPagination;

    public const TABS = ['to_complete' => 'To complete', 'changes' => 'Needs changes', 'submitted' => 'Submitted', 'all' => 'All shows'];

    public const PERIODS = ['month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'];

    public const RANGES = ['' => 'All dates', 'this_month' => 'This month', 'last_month' => 'Last month', '30' => 'Last 30 days'];

    public string $period = 'month';

    public string $tab = 'to_complete';

    public string $search = '';

    public string $range = '';

    public int $perPage = 6;

    public function updated(string $name): void
    {
        if (in_array($name, ['tab', 'search', 'range', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'to_complete';
        $this->resetPage();
    }

    private function streamer(): ?Streamer
    {
        return auth()->user()?->streamer;
    }

    /** Every show they were on, newest first. */
    private function shows(): Builder
    {
        $id = $this->streamer()?->id ?? 0;

        return Show::query()
            ->inChannelContext()
            ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $id))
            ->where('status', '!=', 'cancelled');
    }

    /** Shows that have aired (today's count once their start time passes). */
    private function aired(): Builder
    {
        $now = now()->format('H:i:s');

        return $this->shows()->where(fn ($q) => $q
            ->whereDate('show_date', '<', today())
            ->orWhere(fn ($t) => $t->whereDate('show_date', today())->where(fn ($s) => $s->whereNull('start_time')->orWhereTime('start_time', '<=', $now))));
    }

    private function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            // Same rule as the sidebar badge: since reporting went live, not
            // filed yet, and not closed out already.
            'to_complete' => ShowReportingGoLive::scope($query)
                ->whereNotIn('status', ['closed'])
                ->whereDoesntHave('streamerLogEntry', fn ($q) => $q->whereNotNull('submitted_at')),
            'changes' => $query->whereHas('streamerLogEntry', fn ($q) => $q->where('status', 'changes_requested')),
            'submitted' => $query->whereHas('streamerLogEntry', fn ($q) => $q->whereNotNull('submitted_at')->where('status', '!=', 'changes_requested')),
            default => $query,
        };
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        return collect(array_keys(self::TABS))
            ->mapWithKeys(fn ($tab) => [$tab => $this->applyTab($this->aired(), $tab)->count()])
            ->all();
    }

    /** @return array{0:?Carbon,1:?Carbon} */
    private function rangeDates(): array
    {
        $today = BusinessTime::local();

        return match ($this->range) {
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            '30' => [$today->copy()->subDays(29), $today],
            default => [null, null],
        };
    }

    public function rangeLabel(): string
    {
        [$from, $to] = $this->rangeDates();

        return $from ? $from->format('M j, Y') . ' – ' . $to->format('M j, Y') : 'All dates';
    }

    public function reports()
    {
        [$from, $to] = $this->rangeDates();

        return $this->applyTab($this->aired(), $this->tab)
            ->when(trim($this->search) !== '', fn ($q) => $q->where('title', 'like', '%' . trim($this->search) . '%'))
            ->when($from, fn ($q) => $q->whereBetween('show_date', [$from->toDateString(), $to->toDateString() . ' 23:59:59']))
            ->with(['channel', 'streamerLogEntry' => fn ($q) => $q->withCount('items')
                ->withSum(['items as giveaway_quantity' => fn ($i) => $i->where('disposition', 'giveaway')], 'quantity')])
            ->orderByDesc('show_date')->orderByDesc('start_time')
            ->paginate(in_array($this->perPage, [6, 12, 24], true) ? $this->perPage : 6);
    }

    /** The next show that has not started yet. */
    public function nextShow(): ?Show
    {
        $now = now()->format('H:i:s');

        return $this->shows()->with('channel')
            ->where(fn ($q) => $q->whereDate('show_date', '>', today())
                ->orWhere(fn ($t) => $t->whereDate('show_date', today())->where(fn ($s) => $s->whereNull('start_time')->orWhereTime('start_time', '>', $now))))
            ->orderBy('show_date')->orderBy('start_time')
            ->first();
    }

    /** @return array{shows:int, gross:float, paid:float, unpaid:float, label:string} */
    public function numbers(): array
    {
        $today = BusinessTime::local();
        [$from, $to] = match ($this->period) {
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
        $between = [$from->toDateString(), $to->toDateString() . ' 23:59:59'];
        $inPeriod = fn () => $this->shows()->whereBetween('show_date', $between);
        $pay = fn () => Payout::where('streamer_id', $this->streamer()?->id ?? 0)
            ->whereHas('show', fn ($q) => $q->whereBetween('show_date', $between));

        return [
            'shows' => $inPeriod()->count(),
            'gross' => (float) $inPeriod()->sum('gross_revenue'),
            'paid' => (float) $pay()->where('status', 'paid')->sum('calculated_payout'),
            'unpaid' => (float) $pay()->whereNotIn('status', ['paid', 'draft'])->sum('calculated_payout'),
            'label' => strtolower(self::PERIODS[$this->period] ?? 'this month'),
        ];
    }

    public function render()
    {
        return view('livewire.streamer-home');
    }
}
