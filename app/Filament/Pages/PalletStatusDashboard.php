<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Pallet;
use App\Support\AdminModules;
use Filament\Pages\Page;
use App\Support\NavVisibility;

class PalletStatusDashboard extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Pallet Tracking';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-inbox-stack';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Tracking & History';
    }

    public static function getNavigationSort(): ?int
    {
        return 999;
    }

    public function getView(): string
    {
        return 'filament.pages.pallet-status-dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Track pallet status, receiving history, cost adjustments, and session logs. Everything in one place.';
    }

    public function getPendingPalletsProperty()
    {
        return Pallet::where('status', 'pending')
            ->with('vendor', 'createdBy')
            ->orderBy('expected_delivery_date')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'reference' => $p->reference ?: "Pallet #$p->id",
                'vendor' => $p->vendor?->name,
                'expected_date' => $p->expected_delivery_date?->format('M d, Y'),
                'days_until' => $p->expected_delivery_date ? now()->diffInDays($p->expected_delivery_date) : null,
                'status' => 'pending',
                'tracking_number' => $p->tracking_number,
                'carrier' => $p->carrier,
                'notes' => $p->notes,
            ]);
    }

    public function getShippedPalletsProperty()
    {
        return Pallet::where('status', 'shipped')
            ->with('vendor', 'createdBy')
            ->orderBy('shipped_at')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'reference' => $p->reference ?: "Pallet #$p->id",
                'vendor' => $p->vendor?->name,
                'shipped_at' => $p->shipped_at?->format('M d, Y'),
                'expected_date' => $p->expected_delivery_date?->format('M d, Y'),
                'status' => 'shipped',
                'tracking_number' => $p->tracking_number,
                'carrier' => $p->carrier,
                'in_transit_days' => $p->shipped_at ? now()->diffInDays($p->shipped_at) : 0,
            ]);
    }

    public function getReceivingPalletsProperty()
    {
        return Pallet::where('status', 'receiving')
            ->with('vendor', 'createdBy')
            ->orderByDesc('receiving_started_at')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'reference' => $p->reference ?: "Pallet #$p->id",
                'vendor' => $p->vendor?->name,
                'status' => 'receiving',
                'total_lines' => $p->lines()->count(),
                'lines_received' => $p->lines()->whereNotNull('received_cases_count')->count(),
                'progress' => $p->lines()->count() > 0
                    ? round(($p->lines()->whereNotNull('received_cases_count')->count() / $p->lines()->count()) * 100)
                    : 0,
                'started_at' => $p->receiving_started_at?->format('M d, Y H:i'),
                'total_cases' => $p->totalCasesCount(),
                'received_cases' => $p->receivedCasesCount(),
            ]);
    }

    public function getReceivedPalletsProperty()
    {
        return Pallet::where('status', 'received')
            ->with('vendor', 'createdBy')
            ->orderByDesc('received_date')
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'reference' => $p->reference ?: "Pallet #$p->id",
                'vendor' => $p->vendor?->name,
                'status' => 'received',
                'received_date' => $p->received_date?->format('M d, Y'),
                'total_value' => $p->total_cost ? '$' . number_format($p->total_cost, 2) : 'N/A',
                'total_cases' => $p->totalCasesCount(),
                'total_items' => $p->lines()->sum('count'),
            ]);
    }

    public function getPalletStatsProperty(): array
    {
        return [
            'pending' => Pallet::where('status', 'pending')->count(),
            'shipped' => Pallet::where('status', 'shipped')->count(),
            'receiving' => Pallet::where('status', 'receiving')->count(),
            'received' => Pallet::where('status', 'received')->count(),
            'total_value' => Pallet::whereNotNull('total_cost')->sum('total_cost'),
        ];
    }
}
