<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\ShippingSurcharge;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLoan;
use App\Support\AdminModules;
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
        if ($user?->isOwner()) {
            return true;
        }

        return AdminModules::isEnabled('payouts')
            && AdminModules::isFeatureEnabled('streamer_statement')
            && (bool) $user?->isAdmin();
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
    }

    public function getStreamersListProperty(): \Illuminate\Support\Collection
    {
        return Streamer::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<string,mixed>
     */
    public function getStatementDataProperty(): array
    {
        if (! $this->streamerId) {
            return ['shows' => [], 'totals' => []];
        }

        $from = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();

        $shows = Show::with([
                'payouts'           => fn ($q) => $q->where('streamer_id', $this->streamerId),
                'shippingSurcharges'=> fn ($q) => $q->where('streamer_id', $this->streamerId),
            ])
            ->whereHas('streamers', fn ($q) => $q->where('streamer_id', $this->streamerId))
            ->whereBetween('show_date', [$from, $to])
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
        if (! $this->streamerId) {
            return null;
        }
        return Streamer::find($this->streamerId);
    }
}
