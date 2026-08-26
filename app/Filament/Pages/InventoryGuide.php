<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryLocation;
use App\Support\AdminModules;
use Filament\Pages\Page;

/**
 * The inventory module's counterpart to the receiving guide.
 *
 * Written because the questions people actually ask — "where is the main
 * warehouse", "why does scan-and-add do nothing" — are answered by knowing
 * which screen does which job, and that knowledge lived only in the heads of
 * whoever built it. A printed sheet goes stale in a drawer; this sits one click
 * from the screens it describes.
 */
class InventoryGuide extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Inventory Guide';
    protected static ?string $navigationLabel = 'How It Works';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 99;

    public string $tab = 'cycle';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-guide';
    }

    public function getSubheading(): ?string
    {
        return 'Which screen does which job — and what to do when one argues with you.';
    }

    /**
     * Which screens each tab documents.
     *
     * The guide is filtered by them, so nobody is walked through a page their
     * role cannot open. Being told to "go to Inventory → Locations" by a screen
     * that can see the sidebar you are looking at — and knows the link is not in
     * it — is worse than saying nothing.
     *
     * @return array<string, array{0: string, 1: string, 2: array<int, class-string>}>
     */
    public static function tabDefinitions(): array
    {
        return [
            // First, because it is the only tab that answers "what happens
            // to a box, from arriving to being paid for?". The rest answer
            // "what does this screen do?", which is a different question and
            // the wrong one when you are new.
            'cycle'   => ['🔄', 'Full Cycle', []],
            'start'   => ['📍', 'Start Here', [
                \App\Filament\Resources\InventoryLocationResource::class,
                \App\Filament\Resources\VendorResource::class,
            ]],
            'items'   => ['➕', 'Add & Edit Items', [
                \App\Filament\Resources\InventoryItemResource::class,
                ImportInventorySheet::class,
            ]],
            'restock' => ['📷', 'Restock & Scan', [
                InventoryScanner::class,
                QuickAddContainerScan::class,
            ]],
            'pallets' => ['🚚', 'Stage & Receive', [
                \App\Filament\Resources\PalletResource::class,
                \App\Filament\Resources\ReceivingSessionResource::class,
                PalletStatusDashboard::class,
                PalletReceivingHistory::class,
            ]],
            'costs'   => ['💵', 'Costs & Reports', [
                InventoryValueDashboard::class,
                InventoryReport::class,
                InventoryAnalytics::class,
                InventoryAge::class,
                \App\Filament\Resources\InventoryStockResource::class,
            ]],
            'fix'     => ['🔀', 'Fixing Mistakes', [
                StockTransfer::class,
                InventoryReconciliation::class,
            ]],
            // Always shown: it answers questions about screens you are already
            // looking at, so gating it hides help exactly when it is needed.
            'trouble' => ['🩺', 'Troubleshooting', []],
        ];
    }

    /**
     * Tabs this viewer can actually use.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function getVisibleTabsProperty(): array
    {
        $visible = [];

        foreach (static::tabDefinitions() as $key => [$icon, $label, $pages]) {
            if ($pages === [] || $this->canSee(...$pages)) {
                $visible[$key] = [$icon, $label];
            }
        }

        return $visible;
    }

    /**
     * Whether this viewer can reach any of these screens.
     *
     * canAccess() is the same check the sidebar uses, so the guide cannot drift
     * from what is actually in front of someone. A page that has been removed or
     * renamed counts as not visible rather than taking the guide down with it.
     *
     * @param  class-string  ...$pages
     */
    public function canSee(string ...$pages): bool
    {
        foreach ($pages as $page) {
            try {
                if (class_exists($page) && $page::canAccess()) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }

    public function mount(): void
    {
        // Landing on a tab you cannot see would show a selected button with
        // nothing under it.
        $visible = $this->visibleTabs;

        if (! isset($visible[$this->tab])) {
            $this->tab = (string) array_key_first($visible);
        }
    }

    public function setTab(string $tab): void
    {
        if (isset($this->visibleTabs[$tab])) {
            $this->tab = $tab;
        }
    }

    /**
     * The guide's first instruction is "make sure a location exists", so it may
     * as well answer that for this install rather than describing how to check.
     * A guide that reads the system it documents is worth more than one that
     * asks the reader to go and look.
     *
     * @return array{count: int, hasStorage: bool, names: array<int, string>}
     */
    public function getLocationStatusProperty(): array
    {
        $names = InventoryLocation::where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return [
            'count'      => count($names),
            'hasStorage' => InventoryLocation::where('status', 'active')
                ->where('type', 'main_storage')
                ->exists(),
            'names'      => $names,
        ];
    }
}
