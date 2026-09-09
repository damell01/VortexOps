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

    public static function getNavigationLabel(): string
    {
        $user = auth()->user();
        return ($user?->isStreamer() && ! $user?->isAdmin()) ? 'My Pay & Reports' : 'Streamer Statement';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        $user = auth()->user();
        return ($user?->isStreamer() && ! $user?->isAdmin()) ? 'Streamer' : 'Reports';
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
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        return AdminModules::isEnabled('payouts')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && ((bool) $user?->isAdmin() || (bool) $user?->isStreamer());
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-statement';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return (($user?->isAdmin()) ?? false)
            ? 'Pick a streamer and date range for a printable payout breakdown, show by show.'
            : 'Your show reporting and payout history in one place.';
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

    private function effectiveStreamerId(): ?int
    {
        if ($this->isSelfService) {
            return auth()->user()->streamer?->id ?? 0;
        }

        return $this->streamerId;
    }

    public function getStatementDataProperty(): array
    {
        $streamerId = $this->effectiveStreamerId();

        if (! $streamerId) {
            return ['shows' => [], 'totals' => []];
        }

        $from = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();
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
