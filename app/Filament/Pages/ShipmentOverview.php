<?php

namespace App\Filament\Pages;

use App\Models\Shipment;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ShipmentOverview extends Page
{
    protected static ?string $title = 'Shipment Tracking';
    protected static ?string $slug = 'shipment-tracking';
    protected static bool $shouldRegisterNavigation = false;

    public string $search = '';
    public string $status = 'all';
    public int $days = 30;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    public function getView(): string
    {
        return 'filament.pages.shipment-overview';
    }

    public function dashboardData(): array
    {
        abort_unless(static::canAccess(), 403);
        $base = Shipment::query()->whereHas('show', fn ($query) =>
            $query->where('show_date', '>=', now()->subDays(max(1, min($this->days, 365)))->toDateString()));
        $totals = (clone $base)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')
            ->pluck('total', 'status');
        $rows = (clone $base)->with('show:id,title,show_date,whatnot_channel_id')
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.addcslashes(trim($this->search), '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('buyer_username', 'like', $term)
                        ->orWhere('tracking_number', 'like', $term)
                        ->orWhereHas('show', fn ($show) => $show->where('title', 'like', $term));
                });
            })
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->latest('updated_at')->limit(150)->get();
        return compact('rows', 'totals');
    }
}
