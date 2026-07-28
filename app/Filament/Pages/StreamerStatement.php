<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\ShippingSurcharge;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLoan;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Pages\Page;
use App\Filament\Concerns\HasAdminNavVisibility;

class StreamerStatement extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'Streamer Statement';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Reports';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return AdminModules::isEnabled('payouts')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && ((bool) $user?->isAdmin() || (bool) $user?->isStreamer());
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-statement';
    }

    public ?int $streamerId = null;
    public string $dateFrom = '';
    public string $dateTo   = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->toDateString();

        $user = auth()->user();
        if ($user && $user->isStreamer() && ! $user->isAdmin()) {
            $this->streamerId = $user->streamer?->id;
        }
    }

    /** True when the viewer is locked to their own streamer profile (can't browse others). */
    public function getIsSelfServiceProperty(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->isStreamer() && ! $user->isAdmin());
    }

    public function getStreamersListProperty(): \Illuminate\Support\Collection
    {
        if ($this->isSelfService) {
            $user = auth()->user();

            return Streamer::where('id', $user->streamer?->id ?? 0)->get(['id', 'name']);
        }

        return Streamer::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The streamer id actually used for querying — for self-service viewers this
     * ignores whatever $this->streamerId holds (a public Livewire property can be
     * tampered with client-side) and always forces their own linked profile.
     */
    private function effectiveStreamerId(): ?int
    {
        if ($this->isSelfService) {
            return auth()->user()->streamer?->id ?? 0;
        }

        return $this->streamerId;
    }

    /**
     * @return array<string,mixed>
     */
    public function getStatementDataProperty(): array
    {
        $streamerId = $this->effectiveStreamerId();

        if (! $streamerId) {
            return ['shows' => [], 'totals' => []];
        }

        $from = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();

        // Upper bound needs a time component: show_date rows are stored with a
        // midnight timestamp, which a bare date-string upper bound would exclude
        // via lexical comparison (same gotcha documented in Show::weekPacing()).
        $toDateTime = \Illuminate\Support\Carbon::parse($to)->endOfDay()->toDateTimeString();

        $shows = Show::with([
                'payouts'           => fn ($q) => $q->where('streamer_id', $streamerId),
                'shippingSurcharges'=> fn ($q) => $q->where('streamer_id', $streamerId),
            ])
            ->whereHas('streamers', fn ($q) => $q->where('streamer_id', $streamerId))
            ->whereBetween('show_date', [$from, $toDateTime])
            ->orderBy('show_date')
            ->get();

        $rows         = [];
        $totalGross   = 0;
        $totalDue     = 0;
        $totalPaid    = 0;
        $totalSurcharge = 0;

        foreach ($shows as $show) {
            $payout    = $show->payouts->first();
            $surcharge = $show->shippingSurcharges->first();

            $gross      = (float) $show->gross_revenue;
            $calculated = $payout ? (float) $payout->calculated_payout : 0;
            $surAmt     = $surcharge ? (float) $surcharge->total_amount : 0;
            $net        = $calculated - $surAmt;
            $paid       = $payout && $payout->status === 'paid' ? $calculated : 0;

            $totalGross     += $gross;
            $totalDue       += $calculated;
            $totalSurcharge += $surAmt;
            $totalPaid      += $paid;

            $rows[] = [
                'show_date'   => $show->show_date?->toDateString(),
                'title'       => $show->title,
                'gross'       => $gross,
                'payout_type' => $payout?->payout_type ?? '—',
                'calculated'  => $calculated,
                'surcharge'   => $surAmt,
                'net_payout'  => $net,
                'paid'        => $paid,
                'status'      => $payout?->status ?? 'no payout',
            ];
        }

        $outstanding = $totalDue - $totalPaid;

        return [
            'shows'  => $rows,
            'totals' => [
                'gross'       => $totalGross,
                'due'         => $totalDue,
                'surcharge'   => $totalSurcharge,
                'paid'        => $totalPaid,
                'outstanding' => $outstanding,
            ],
        ];
    }

    public function getSelectedStreamerProperty(): ?Streamer
    {
        $streamerId = $this->effectiveStreamerId();

        if (! $streamerId) {
            return null;
        }
        return Streamer::find($streamerId);
    }
}
