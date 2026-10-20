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

    public const TABS = ['to_complete' => 'To complete', 'changes' => 'Needs changes', 'submitted' => 'Submitted', 'upcoming' => 'Upcoming', 'all' => 'All shows'];

    public const PERIODS = ['week' => 'This week', 'last_week' => 'Last week', 'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year', 'last_year' => 'Last year'];

    public const RANGES = ['' => 'All dates', 'this_month' => 'This month', 'last_month' => 'Last month', '30' => 'Last 30 days'];

    public string $period = 'week';

    public string $tab = 'to_complete';

    public string $search = '';

    public string $range = '';

    public int $perPage = 6;

    /** The show whose filed report the streamer is asking to reopen. */
    public ?int $revisionFor = null;

    public string $revisionReason = '';

    /** Ask which Pokémon they want, until they choose or skip it this session. */
    public bool $buddyPrompt = false;

    public function mount(): void
    {
        $this->buddyPrompt = ! \App\Support\Buddy::chosen(auth()->user()) && ! session('buddy_skipped');
    }

    public function chooseBuddy(string $buddy): void
    {
        if (! array_key_exists($buddy, \App\Support\Buddy::options())) {
            return;
        }
        auth()->user()->forceFill(['buddy' => $buddy])->save();
        $this->buddyPrompt = false;
    }

    public function skipBuddy(): void
    {
        session(['buddy_skipped' => true]);
        $this->buddyPrompt = false;
    }

    public function openBuddyPicker(): void
    {
        $this->buddyPrompt = true;
    }

    public function updated(string $name): void
    {
        if ($name === 'period' && ! array_key_exists($this->period, self::PERIODS)) $this->period = 'week';
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
            ->mapWithKeys(fn ($tab) => [$tab => $tab === 'upcoming' ? $this->upcoming()->count() : $this->applyTab($this->aired(), $tab)->count()])
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

        $base = $this->tab === 'upcoming' ? $this->upcoming() : $this->applyTab($this->aired(), $this->tab);
        $streamerId = $this->streamer()?->id ?? 0;

        return $base
            ->when(trim($this->search) !== '', fn ($q) => $q->where('title', 'like', '%' . trim($this->search) . '%'))
            ->when($from, fn ($q) => $q->whereBetween('show_date', [$from->toDateString(), $to->toDateString() . ' 23:59:59']))
            ->with(['channel', 'streamerLogEntry' => fn ($q) => $q->withCount('items')
                ->withSum(['items as giveaway_quantity' => fn ($i) => $i->where('disposition', 'giveaway')], 'quantity'),
                'payouts' => fn ($q) => $q->where('streamer_id', $streamerId)->select('id', 'show_id', 'streamer_id', 'status', 'calculated_payout')])
            ->when($this->tab === 'upcoming', fn ($q) => $q->reorder()->orderBy('show_date')->orderBy('start_time'), fn ($q) => $q->orderByDesc('show_date')->orderByDesc('start_time'))
            ->paginate(in_array($this->perPage, [6, 12, 24], true) ? $this->perPage : 6);
    }

    /** Shows that have not started yet. */
    private function upcoming(): Builder
    {
        $now = now()->format('H:i:s');

        // A show today with no start time counts as aired (it is on To complete).
        return $this->shows()
            ->where(fn ($q) => $q->whereDate('show_date', '>', today())
                ->orWhere(fn ($t) => $t->whereDate('show_date', today())->whereNotNull('start_time')->whereTime('start_time', '>', $now)));
    }

    /** The next show that has not started yet. */
    public function nextShow(): ?Show
    {
        return $this->upcoming()->with('channel')->orderBy('show_date')->orderBy('start_time')->first();
    }

    /** Reports waiting on this streamer — the sidebar badge. */
    public static function toCompleteCount(): int
    {
        return (new static)->applyTab((new static)->aired(), 'to_complete')->count();
    }

    // ── Asking for a filed report back ───────────────────────────────────────

    public function askForChanges(int $showId): void
    {
        $this->revisionFor = $showId;
        $this->revisionReason = '';
    }

    public function cancelRevisionRequest(): void
    {
        $this->revisionFor = null;
        $this->revisionReason = '';
    }

    public function revisionShow(): ?Show
    {
        return $this->revisionFor ? $this->shows()->with('streamerLogEntry')->find($this->revisionFor) : null;
    }

    public function submitRevisionRequest(): void
    {
        // Through shows(), so a streamer can only ask about a show they were on.
        $show = $this->revisionShow();
        $entry = $show?->streamerLogEntry;

        if (! $entry || ! $entry->canRequestRevision()) {
            $this->cancelRevisionRequest();

            return;
        }

        $entry->requestRevision($this->revisionReason);
        \App\Services\Notifier::send('report_reopen_requested', new \App\Notifications\VortexAlert(
            event: 'report_reopen_requested',
            title: 'A streamer wants to change a filed report',
            body: trim(($show->title ?? 'Show #' . $show->id) . ' — ' . ($entry->revision_reason ?: 'no reason given')),
            tone: 'warning',
            links: ['Open the report' => \App\Filament\Resources\StreamerLogResource::getUrl('index')],
        ));

        $this->cancelRevisionRequest();

        \Filament\Notifications\Notification::make()
            ->title('Asked for changes')
            ->body('An admin has been told. The report stays as filed until they reopen it.')
            ->success()
            ->send();
        $this->dispatch('vx-poke', kind: 'ask');
    }

    /** These figures are grouped by the show's local date, not bank transfer date. */
    public function performanceDates(): array
    {
        $today = BusinessTime::local();
        return match ($this->period) {
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_week' => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            default => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
        };
    }

    public function numbers(): array
    {
        [$from, $to] = $this->performanceDates();
        $now = BusinessTime::local();
        $between = [$from->toDateString(), $to->toDateString() . ' 23:59:59'];
        $streamerId = $this->streamer()?->id ?? 0;
        $started = fn (Show $s) => ! $s->startsAt(BusinessTime::zone())?->gt($now);
        $shows = $this->shows()->whereBetween('show_date', $between)
            ->whereDate('show_date', '<=', $now->toDateString())
            ->with(['streamerLogEntry' => fn ($q) => $q->where('streamer_id', $streamerId)])
            ->get()->filter($started);
        // Resolve ownership from the payout itself: old paid shows remain in a
        // person's pay totals even if their show assignment was later removed.
        $pay = Payout::where('streamer_id', $streamerId)
            ->whereHas('show', fn ($q) => $q->inChannelContext()->whereBetween('show_date', $between)
                ->whereDate('show_date', '<=', $now->toDateString())->where('status', '!=', 'cancelled'))
            ->with(['show', 'batch:id,status'])->get()->filter(fn ($p) => $started($p->show));
        $isPaid = fn ($p) => $p->status === 'paid' || $p->batch?->status === 'paid';
        $isApproved = fn ($p) => $p->status === 'approved' || in_array($p->batch?->status, ['finalized', 'submitted_to_adp'], true);
        $paid = $pay->filter($isPaid);
        $unpaid = $pay->reject($isPaid);
        $approved = $unpaid->filter($isApproved);
        $draft = $unpaid->reject($isApproved)->whereIn('status', ['draft', 'pending']);
        $earnings = $shows->map(fn ($s) => \App\Support\ProfitShareFormula::earningsForShow($s));
        $timed = $shows->map(function ($show) {
            $hours = $show->show_duration > 0 ? (float) $show->show_duration / 60 : (float) ($show->streamerLogEntry?->hours_streamed ?? 0);
            return ['hours' => $hours, 'gross' => (float) $show->gross_revenue];
        })->filter(fn ($s) => $s['hours'] > 0);
        $hours = (float) $timed->sum('hours');
        $gross = (float) $shows->sum('gross_revenue');
        $best = $shows->sortByDesc('gross_revenue')->first();

        return [
            'shows' => $shows->count(), 'gross' => $gross,
            'paid' => (float) $paid->sum('calculated_payout'),
            'unpaid' => (float) $approved->sum('calculated_payout'),
            'draft_pay' => (float) $draft->sum('calculated_payout'),
            'earnings' => $earnings->contains(fn ($v) => $v !== null) ? (float) $earnings->sum() : null,
            'earnings_pending' => $earnings->filter(fn ($v) => $v === null)->count(),
            'hours' => round($hours, 2), 'timed_shows' => $timed->count(),
            'gross_per_hour' => $hours > 0 ? round($timed->sum('gross') / $hours, 2) : null,
            'gross_per_show' => $shows->isNotEmpty() ? round($gross / $shows->count(), 2) : null,
            'units' => (int) $shows->sum('units_sold'),
            'best_show' => $best?->title, 'best_gross' => $best ? (float) $best->gross_revenue : null,
            'label' => strtolower(self::PERIODS[$this->period] ?? 'this week'),
            'dates' => $from->format('M j').' – '.$to->format('M j, Y'),
        ];
    }

    public function render()
    {
        return view('livewire.streamer-home');
    }
}
