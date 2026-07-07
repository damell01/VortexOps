<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Models\Product;
use App\Models\ProductIdentity;
use App\Models\ReceivingSession;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProductHealthDashboard extends Page
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'purchasing';
    protected static string $featureSlug = 'catalog_intelligence';
    protected static ?string $title = 'Catalog Intelligence';
    protected static ?string $navigationLabel = 'Catalog Intelligence';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public function getView(): string
    {
        return 'filament.pages.product-health-dashboard';
    }

    // ── Metrics ────────────────────────────────────────────────────────────────

    public function getMetricsProperty(): array
    {
        $total        = Product::count();
        $active       = Product::where('is_active', true)->count();
        $withUpc      = Product::whereNotNull('upc')->where('upc', '!=', '')->count();
        $missingUpc   = $active - $withUpc;
        $withAliases  = ProductIdentity::distinct('product_id')->count('product_id');
        $totalAliases = ProductIdentity::count();

        $avgConf = ProductIdentity::where('times_confirmed', '>', 0)
            ->avg('auto_confidence') ?? 0.0;

        $neverReceived = Product::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('total_units_received')->orWhere('total_units_received', 0))
            ->count();

        $sessions     = ReceivingSession::where('status', 'completed')->count();
        $autoRate     = ReceivingSession::where('status', 'completed')->where('total_lines', '>', 0)
            ->selectRaw('AVG(auto_matched_count / total_lines * 100) as rate')
            ->value('rate') ?? 0;

        return [
            'total_products'   => $total,
            'active_products'  => $active,
            'missing_upc'      => $missingUpc,
            'with_upc_pct'     => $total > 0 ? round($withUpc / $total * 100) : 0,
            'with_aliases'     => $withAliases,
            'total_aliases'    => $totalAliases,
            'avg_confidence'   => round($avgConf * 100, 1),
            'never_received'   => $neverReceived,
            'sessions_done'    => $sessions,
            'auto_match_rate'  => round($autoRate, 1),
        ];
    }

    public function getMissingUpcProductsProperty(): array
    {
        return Product::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('upc')->orWhere('upc', ''))
            ->select(['id', 'name', 'brand', 'year', 'sport', 'average_cost'])
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'label' => $p->brand . ($p->year ? " {$p->year}" : ''),
                'cost'  => number_format((float) $p->average_cost, 2),
            ])->toArray();
    }

    public function getMostAliasedProperty(): array
    {
        return ProductIdentity::selectRaw('product_id, COUNT(*) as alias_count, SUM(times_confirmed) as total_confirms')
            ->with('product:id,name,brand,year')
            ->groupBy('product_id')
            ->orderByDesc('alias_count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'product'        => $r->product?->name ?? '—',
                'alias_count'    => $r->alias_count,
                'total_confirms' => $r->total_confirms,
            ])->toArray();
    }

    public function getMostReceivedProperty(): array
    {
        return Product::select(['products.id', 'products.name', 'products.brand', 'products.year', 'products.total_units_received'])
            ->where('is_active', true)
            ->whereNotNull('total_units_received')
            ->where('total_units_received', '>', 0)
            ->orderByDesc('total_units_received')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'name'      => $p->name,
                'received'  => number_format((float) $p->total_units_received),
                'avg_cost'  => number_format((float) $p->average_cost, 2),
            ])->toArray();
    }

    public function getLowConfidenceProductsProperty(): array
    {
        return ProductIdentity::selectRaw('product_id, AVG(auto_confidence) as avg_conf, COUNT(*) as cnt')
            ->with('product:id,name')
            ->groupBy('product_id')
            ->having('avg_conf', '<', 0.85)
            ->having('cnt', '>', 0)
            ->orderBy('avg_conf')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'product'    => $r->product?->name ?? '—',
                'confidence' => round((float) $r->avg_conf * 100, 1),
                'aliases'    => $r->cnt,
            ])->toArray();
    }
}
