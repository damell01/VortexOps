<?php

namespace App\Services;

use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiContextBuilder
{
    public function buildSystemPrompt(string $path): string
    {
        $business = $this->businessSummary();
        $page     = $this->pageContext($path);

        return implode("\n\n", array_filter([
            "You are Vortex AI for Vortex Breaks (Whatnot sports card break business). Answer concisely using the data below. No disclaimers.",
            $business,
            $page,
        ]));
    }

    private function businessSummary(): string
    {
        return Cache::remember('ai_business_summary', 60, function () {
            $shows      = Show::count();
            $active     = Show::whereNotIn('status', ['closed', 'cancelled'])->count();
            $streamers  = Streamer::where('status', 'active')->count();
            $items      = InventoryItem::where('is_active', true)->count();
            $stockValue = InventoryStock::join('products', 'products.id', '=', 'inventory_stock.inventory_item_id')
                ->selectRaw('SUM(inventory_stock.quantity * products.average_cost) as total')
                ->value('total') ?? 0;

            $recentShows = Show::whereNotIn('status', ['cancelled'])
                ->orderBy('show_date', 'desc')
                ->limit(5)
                ->get(['title', 'show_date', 'status', 'gross_revenue'])
                ->map(fn ($s) => "  - {$s->title} ({$s->show_date?->format('M j')}): {$s->status}" . ($s->gross_revenue ? ', $' . number_format($s->gross_revenue, 0) . ' gross' : ''))
                ->join("\n");

            $pendingDeductions = DeductionRequest::whereIn('status', ['pending', 'draft'])->count();

            return implode("\n", [
                "## Business Snapshot",
                "- Total shows: {$shows} ({$active} active)",
                "- Active streamers: {$streamers}",
                "- Inventory items: {$items}",
                "- Estimated stock value: \$" . number_format($stockValue, 0),
                "- Deduction requests awaiting action: {$pendingDeductions}",
                "",
                "### Recent Shows",
                $recentShows ?: "  (none)",
            ]);
        });
    }

    private function pageContext(string $path): string
    {
        // /admin/shows/123
        if (preg_match('#/admin/shows/(\d+)#', $path, $m)) {
            return $this->showContext((int) $m[1]);
        }

        // /admin/shows
        if (preg_match('#/admin/shows#', $path)) {
            return $this->showsListContext();
        }

        // /admin/payouts/123
        if (preg_match('#/admin/payouts/(\d+)#', $path, $m)) {
            return $this->payoutContext((int) $m[1]);
        }

        // /admin/payouts
        if (preg_match('#/admin/payouts#', $path)) {
            return $this->payoutsListContext();
        }

        // /admin/deduction-requests/123
        if (preg_match('#/admin/deduction-requests/(\d+)#', $path, $m)) {
            return $this->deductionContext((int) $m[1]);
        }

        // /admin/streamers/123
        if (preg_match('#/admin/streamers/(\d+)#', $path, $m)) {
            return $this->streamerContext((int) $m[1]);
        }

        // /admin/inventory-items/123
        if (preg_match('#/admin/inventory-items/(\d+)#', $path, $m)) {
            return $this->inventoryItemContext((int) $m[1]);
        }

        // /admin/inventory-items
        if (preg_match('#/admin/inventory-items#', $path)) {
            return $this->inventoryListContext();
        }

        return '';
    }

    private function showContext(int $id): string
    {
        $show = Show::with(['streamers', 'payouts.streamer', 'orders', 'latestDeductionRequest.lines'])->find($id);
        if (! $show) {
            return "## Current Page\nShow #{$id} not found.";
        }

        $streamers = $show->streamers->pluck('name')->join(', ') ?: 'none assigned';
        $orders    = $show->orders->count();
        $dr        = $show->latestDeductionRequest;
        $drInfo    = $dr
            ? "Deduction request #{$dr->id} ({$dr->status}), {$dr->lines->count()} lines"
            : 'No deduction request yet';

        $payoutInfo = $show->payouts->isEmpty()
            ? 'No payouts'
            : $show->payouts->map(fn ($p) => "{$p->streamer?->name}: \${$p->net_payout}")->join('; ');

        return implode("\n", [
            "## Current Page — Show: {$show->title}",
            "- Date: " . ($show->show_date?->format('M j, Y') ?? '—'),
            "- Status: {$show->status}",
            "- Streamers: {$streamers}",
            "- Gross revenue: \$" . number_format((float) $show->gross_revenue, 2),
            "- Units sold: " . ($show->units_sold ?? '—'),
            "- Imported orders: {$orders}",
            "- {$drInfo}",
            "- Payouts: {$payoutInfo}",
        ]);
    }

    private function showsListContext(): string
    {
        $byStatus = Show::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $lines    = collect(Show::statusLabels())->map(fn ($label, $key) => "  {$label}: " . ($byStatus[$key] ?? 0))->join("\n");

        return "## Current Page — Shows List\n{$lines}";
    }

    private function payoutContext(int $id): string
    {
        $payout = Payout::with(['show', 'streamer'])->find($id);
        if (! $payout) {
            return "## Current Page\nPayout #{$id} not found.";
        }

        return implode("\n", [
            "## Current Page — Payout #{$id}",
            "- Streamer: " . ($payout->streamer?->name ?? '—'),
            "- Show: " . ($payout->show?->title ?? '—') . ' (' . ($payout->show?->show_date?->format('M j, Y') ?? '—') . ')',
            "- Type: {$payout->payout_type}",
            "- Gross revenue: \$" . number_format((float) $payout->gross_revenue, 2),
            "- Net payout: \$" . number_format((float) $payout->net_payout, 2),
            "- Status: {$payout->status}",
        ]);
    }

    private function payoutsListContext(): string
    {
        $pending = Payout::where('status', 'pending')->count();
        $total   = Payout::sum('net_payout');
        $ytd     = Payout::whereYear('created_at', now()->year)->sum('net_payout');

        return implode("\n", [
            "## Current Page — Payouts List",
            "- Pending payouts: {$pending}",
            "- All-time total paid: \$" . number_format($total, 2),
            "- Year-to-date total: \$" . number_format($ytd, 2),
        ]);
    }

    private function deductionContext(int $id): string
    {
        $dr = DeductionRequest::with(['show', 'streamer', 'lines.inventoryItem'])->find($id);
        if (! $dr) {
            return "## Current Page\nDeduction request #{$id} not found.";
        }

        $mapped   = $dr->lines->whereNotNull('inventory_item_id')->count();
        $unmapped = $dr->lines->whereNull('inventory_item_id')->count();
        $total    = $dr->lines->sum('line_total');

        return implode("\n", [
            "## Current Page — Deduction Request #{$id}",
            "- Show: " . ($dr->show?->title ?? '—'),
            "- Streamer: " . ($dr->streamer?->name ?? '—'),
            "- Status: {$dr->status}",
            "- Lines: {$dr->lines->count()} ({$mapped} mapped, {$unmapped} unmatched)",
            "- Total deduction value: \$" . number_format($total, 2),
        ]);
    }

    private function streamerContext(int $id): string
    {
        $streamer = Streamer::find($id);
        if (! $streamer) {
            return "## Current Page\nStreamer #{$id} not found.";
        }

        $showCount   = Show::whereHas('streamers', fn ($q) => $q->where('streamers.id', $id))->count();
        $totalPaid   = Payout::where('streamer_id', $id)->where('status', 'paid')->sum('net_payout');
        $pendingPaid = Payout::where('streamer_id', $id)->where('status', 'pending')->sum('net_payout');

        return implode("\n", [
            "## Current Page — Streamer: {$streamer->name}",
            "- Status: {$streamer->status}",
            "- Payout type: {$streamer->payout_type}",
            "- Shows: {$showCount}",
            "- Total paid: \$" . number_format($totalPaid, 2),
            "- Pending payout: \$" . number_format($pendingPaid, 2),
        ]);
    }

    private function inventoryItemContext(int $id): string
    {
        $item = InventoryItem::find($id);
        if (! $item) {
            return "## Current Page\nInventory item #{$id} not found.";
        }

        $totalQty = InventoryStock::where('inventory_item_id', $id)->sum('quantity');

        return implode("\n", [
            "## Current Page — Inventory: {$item->name}",
            "- SKU: " . ($item->sku ?? '—'),
            "- Category: " . ($item->category ?? '—'),
            "- Average cost: \$" . number_format((float) $item->average_cost, 2),
            "- Total stock: {$totalQty} units",
            "- Active: " . ($item->is_active ? 'yes' : 'no'),
        ]);
    }

    private function inventoryListContext(): string
    {
        $active   = InventoryItem::where('is_active', true)->count();
        $inactive = InventoryItem::where('is_active', false)->count();
        $lowStock = InventoryStock::selectRaw('inventory_item_id, SUM(quantity) as qty')
            ->groupBy('inventory_item_id')
            ->having('qty', '<=', 2)
            ->count();

        return implode("\n", [
            "## Current Page — Inventory List",
            "- Active items: {$active}",
            "- Inactive items: {$inactive}",
            "- Items at or below 2 units: {$lowStock}",
        ]);
    }
}
