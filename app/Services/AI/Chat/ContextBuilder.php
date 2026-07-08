<?php

namespace App\Services\AI\Chat;

use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\Payout;
use App\Models\ReceivingSession;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\Vendor;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Support\Facades\Cache;

/**
 * Builds page-scoped context blocks injected into every AI system prompt.
 * Business snapshot is cached per-minute; page context is always fresh.
 */
class ContextBuilder
{
    // ── Entry points ──────────────────────────────────────────────────────────

    public function buildSystemContext(string $path): string
    {
        return implode("\n\n", array_filter([
            $this->buildBusinessSummary(),
            $this->buildPageContext($path),
        ]));
    }

    public function buildBusinessSummary(): string
    {
        return Cache::remember('ai_business_summary_v2', 60, function () {
            $shows     = Show::count();
            $active    = Show::whereNotIn('status', ['closed', 'cancelled'])->count();
            $streamers = Streamer::where('status', 'active')->count();
            $items     = InventoryItem::where('is_active', true)->count();

            $stockValue = InventoryStock::join('products', 'products.id', '=', 'inventory_stock.inventory_item_id')
                ->selectRaw('SUM(inventory_stock.quantity * products.average_cost) as total')
                ->value('total') ?? 0;

            $pendingDeductions = DeductionRequest::whereIn('status', ['pending', 'draft'])->count();
            $pendingPayouts    = Payout::where('status', 'pending')->count();

            $recentShows = Show::whereNotIn('status', ['cancelled'])
                ->orderByDesc('show_date')
                ->limit(5)
                ->get(['title', 'show_date', 'status', 'gross_revenue'])
                ->map(fn ($s) =>
                    "  - {$s->title} ({$s->show_date?->format('M j')}): {$s->status}"
                    . ($s->gross_revenue ? ', $' . number_format($s->gross_revenue, 0) . ' gross' : ''))
                ->join("\n");

            return implode("\n", [
                '## Business Snapshot',
                "- Total shows: {$shows} ({$active} active/open)",
                "- Active streamers: {$streamers}",
                "- Inventory items: {$items} | Est. stock value: \$" . number_format($stockValue, 0),
                "- Deduction requests awaiting action: {$pendingDeductions}",
                "- Payouts pending: {$pendingPayouts}",
                '',
                '### Recent Shows',
                $recentShows ?: '  (none)',
            ]);
        });
    }

    public function buildPageContext(string $path): string
    {
        // /admin/shows/123
        if (preg_match('#/admin/shows/(\d+)#', $path, $m)) {
            return $this->showContext((int) $m[1]);
        }
        if (preg_match('#/admin/shows#', $path)) {
            return $this->showsListContext();
        }

        // /admin/payouts/123
        if (preg_match('#/admin/payouts/(\d+)#', $path, $m)) {
            return $this->payoutContext((int) $m[1]);
        }
        if (preg_match('#/admin/payouts#', $path)) {
            return $this->payoutsListContext();
        }

        // /admin/weekly-payout-batches/123
        if (preg_match('#/admin/weekly-payout-batches/(\d+)#', $path, $m)) {
            return $this->weeklyBatchContext((int) $m[1]);
        }

        // /admin/deduction-requests/123
        if (preg_match('#/admin/deduction-requests/(\d+)#', $path, $m)) {
            return $this->deductionContext((int) $m[1]);
        }
        if (preg_match('#/admin/deduction-requests#', $path)) {
            return $this->deductionsListContext();
        }

        // /admin/streamers/123
        if (preg_match('#/admin/streamers/(\d+)#', $path, $m)) {
            return $this->streamerContext((int) $m[1]);
        }
        if (preg_match('#/admin/streamers#', $path)) {
            return $this->streamersListContext();
        }

        // /admin/inventory-items/123
        if (preg_match('#/admin/inventory-items/(\d+)#', $path, $m)) {
            return $this->inventoryItemContext((int) $m[1]);
        }
        if (preg_match('#/admin/inventory-items#', $path)) {
            return $this->inventoryListContext();
        }

        // /admin/pallets/123
        if (preg_match('#/admin/pallets/(\d+)#', $path, $m)) {
            return $this->palletContext((int) $m[1]);
        }
        if (preg_match('#/admin/pallets#', $path)) {
            return $this->palletsListContext();
        }

        // /admin/receiving-sessions/123
        if (preg_match('#/admin/receiving-sessions/(\d+)#', $path, $m)) {
            return $this->receivingSessionContext((int) $m[1]);
        }

        // /admin/vendors/123
        if (preg_match('#/admin/vendors/(\d+)#', $path, $m)) {
            return $this->vendorContext((int) $m[1]);
        }

        // /admin/streamer-log-entries or /admin/ledger
        if (str_contains($path, '/streamer-log')) {
            return $this->streamerLogsContext();
        }
        if (str_contains($path, '/ledger')) {
            return $this->ledgerContext();
        }

        return '';
    }

    // ── Page contexts ─────────────────────────────────────────────────────────

    private function showContext(int $id): string
    {
        $show = Show::with([
            'streamers',
            'payouts.streamer',
            'orders',
            'latestDeductionRequest.lines.inventoryItem',
        ])->find($id);

        if (! $show) {
            return "## Current Page\nShow #{$id} not found.";
        }

        $streamers  = $show->streamers->pluck('name')->join(', ') ?: 'none assigned';
        $orders     = $show->orders->count();
        $dr         = $show->latestDeductionRequest;
        $drMapped   = $dr ? $dr->lines->whereNotNull('inventory_item_id')->count() : 0;
        $drTotal    = $dr ? $dr->lines->count() : 0;
        $drInfo     = $dr
            ? "DR #{$dr->id} ({$dr->status}) — {$drMapped}/{$drTotal} lines mapped"
            : 'No deduction request yet';

        $payoutInfo = $show->payouts->isEmpty()
            ? 'No payouts'
            : $show->payouts->map(fn ($p) => "{$p->streamer?->name}: \${$p->calculated_payout} [{$p->status}]")->join('; ');

        return implode("\n", [
            "## Current Page — Show: {$show->title}",
            '- Date: ' . ($show->show_date?->format('M j, Y') ?? '—'),
            "- Status: {$show->status}",
            "- Streamers: {$streamers}",
            '- Gross revenue: $' . number_format((float) $show->gross_revenue, 2),
            '- Whatnot net: $' . number_format((float) $show->whatnot_net, 2),
            '- Tips: $' . number_format((float) $show->tips, 2),
            "- Units sold: " . ($show->units_sold ?? '—'),
            "- Imported orders: {$orders}",
            "- {$drInfo}",
            "- Payouts: {$payoutInfo}",
        ]);
    }

    private function showsListContext(): string
    {
        $byStatus = Show::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $needsWork = Show::whereIn('status', ['pending_review', 'mapping', 'pending_approval'])->count();
        return implode("\n", [
            '## Current Page — Shows List',
            '- By status: ' . $byStatus->map(fn ($c, $k) => "{$k}={$c}")->join(', '),
            "- Shows needing action: {$needsWork}",
        ]);
    }

    private function payoutContext(int $id): string
    {
        $payout = Payout::with(['show', 'streamer'])->find($id);

        if (! $payout) {
            return "## Current Page\nPayout #{$id} not found.";
        }

        return implode("\n", [
            "## Current Page — Payout #{$id}",
            '- Streamer: ' . ($payout->streamer?->name ?? '—'),
            '- Show: ' . ($payout->show?->title ?? '—') . ' (' . ($payout->show?->show_date?->format('M j, Y') ?? '—') . ')',
            "- Type: {$payout->payout_type}",
            '- Gross revenue: $' . number_format((float) $payout->gross_show_revenue, 2),
            '- Calculated payout: $' . number_format((float) $payout->calculated_payout, 2),
            "- Status: {$payout->status}",
            '- Notes: ' . ($payout->calculation_notes ? substr($payout->calculation_notes, 0, 200) : '—'),
        ]);
    }

    private function payoutsListContext(): string
    {
        $pending = Payout::where('status', 'pending')->count();
        $pendingTotal = Payout::where('status', 'pending')->sum('calculated_payout');
        $ytd     = Payout::whereYear('created_at', now()->year)->sum('calculated_payout');

        return implode("\n", [
            '## Current Page — Payouts List',
            "- Pending payouts: {$pending} (total: \$" . number_format($pendingTotal, 2) . ')',
            '- YTD paid: $' . number_format($ytd, 2),
        ]);
    }

    private function weeklyBatchContext(int $id): string
    {
        $batch = WeeklyPayoutBatch::with(['payouts.streamer'])->find($id);

        if (! $batch) {
            return "## Current Page\nPay batch #{$id} not found.";
        }

        $breakdown = $batch->payouts
            ->sortByDesc('calculated_payout')
            ->map(fn ($p) => "  - {$p->streamer?->name}: \${$p->calculated_payout} [{$p->status}]")
            ->join("\n");

        return implode("\n", [
            "## Current Page — Weekly Pay Batch #{$id}",
            '- Week: ' . $batch->week_start?->format('M j') . ' – ' . $batch->week_end?->format('M j, Y'),
            '- Total payout: $' . number_format((float) $batch->total_payout, 2),
            "- Status: {$batch->status}",
            "- Payouts: {$batch->payouts->count()} streamers",
            "### Breakdown\n{$breakdown}",
        ]);
    }

    private function deductionContext(int $id): string
    {
        $dr = DeductionRequest::with(['show', 'streamer', 'lines.inventoryItem'])->find($id);

        if (! $dr) {
            return "## Current Page\nDeduction request #{$id} not found.";
        }

        $mapped    = $dr->lines->whereNotNull('inventory_item_id')->count();
        $unmapped  = $dr->lines->whereNull('inventory_item_id')->count();
        $highConf  = $dr->lines->where('ai_confidence', 'high')->count();
        $total     = $dr->lines->sum('line_total');

        $sampleLines = $dr->lines->take(5)
            ->map(fn ($l) => "  - {$l->raw_description} → " . ($l->inventoryItem?->name ?? 'UNMATCHED') . " [{$l->ai_confidence}]")
            ->join("\n");

        return implode("\n", [
            "## Current Page — Deduction Request #{$id}",
            '- Show: ' . ($dr->show?->title ?? '—'),
            '- Streamer: ' . ($dr->streamer?->name ?? '—'),
            "- Status: {$dr->status}",
            "- Lines: {$dr->lines->count()} ({$mapped} matched, {$unmapped} unmatched, {$highConf} high-confidence)",
            '- Total deduction value: $' . number_format($total, 2),
            "### Sample lines\n{$sampleLines}",
        ]);
    }

    private function deductionsListContext(): string
    {
        $byStatus = DeductionRequest::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        return implode("\n", [
            '## Current Page — Deduction Requests',
            '- By status: ' . $byStatus->map(fn ($c, $k) => "{$k}={$c}")->join(', '),
        ]);
    }

    private function streamerContext(int $id): string
    {
        $streamer = Streamer::find($id);

        if (! $streamer) {
            return "## Current Page\nStreamer #{$id} not found.";
        }

        $showCount   = Show::whereHas('streamers', fn ($q) => $q->where('streamers.id', $id))->count();
        $totalPaid   = Payout::where('streamer_id', $id)->where('status', 'paid')->sum('calculated_payout');
        $pending     = Payout::where('streamer_id', $id)->whereIn('status', ['pending', 'draft'])->sum('calculated_payout');
        $balance     = max(0, (float) $streamer->total_earnings_due - (float) $streamer->total_earnings_paid);

        return implode("\n", [
            "## Current Page — Streamer: {$streamer->name}",
            "- Status: {$streamer->status}",
            "- Payout type: {$streamer->payout_type}",
            "- Shows: {$showCount}",
            '- Lifetime paid: $' . number_format($totalPaid, 2),
            '- Pending payouts: $' . number_format($pending, 2),
            '- Outstanding balance: $' . number_format($balance, 2),
        ]);
    }

    private function streamersListContext(): string
    {
        $active   = Streamer::where('status', 'active')->count();
        $totalOwed = Streamer::where('status', 'active')->selectRaw('SUM(total_earnings_due - total_earnings_paid) as owed')->value('owed') ?? 0;

        return implode("\n", [
            '## Current Page — Streamers List',
            "- Active streamers: {$active}",
            '- Total outstanding balance: $' . number_format($totalOwed, 2),
        ]);
    }

    private function inventoryItemContext(int $id): string
    {
        $item = InventoryItem::with('stock.location')->find($id);

        if (! $item) {
            return "## Current Page\nInventory item #{$id} not found.";
        }

        $totalQty = $item->stock->sum('quantity');
        $byLocation = $item->stock
            ->map(fn ($s) => "  - {$s->location?->name}: {$s->quantity}")
            ->join("\n");

        return implode("\n", [
            "## Current Page — Inventory: {$item->name}",
            '- SKU: ' . ($item->sku ?? '—'),
            '- Category: ' . ($item->category ?? '—'),
            '- Unit cost (list): $' . number_format((float) $item->unit_cost, 2),
            '- Average cost (WAC): $' . number_format((float) $item->average_cost, 4),
            "- Total units in stock: {$totalQty}",
            '- Reorder level: ' . ($item->reorder_level ?? 'not set'),
            '- Active: ' . ($item->is_active ? 'yes' : 'no'),
            "### Stock by location\n{$byLocation}",
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

        $stockValue = InventoryStock::join('products', 'products.id', '=', 'inventory_stock.inventory_item_id')
            ->selectRaw('SUM(inventory_stock.quantity * products.average_cost) as total')
            ->value('total') ?? 0;

        return implode("\n", [
            '## Current Page — Inventory List',
            "- Active items: {$active} | Inactive: {$inactive}",
            "- Items at or below 2 units: {$lowStock}",
            '- Estimated total stock value: $' . number_format($stockValue, 2),
        ]);
    }

    private function palletContext(int $id): string
    {
        $pallet = Pallet::with(['vendor', 'palletLines.inventoryItem'])->find($id);

        if (! $pallet) {
            return "## Current Page\nPallet #{$id} not found.";
        }

        $received = $pallet->palletLines->where('status', 'received')->count();
        $total    = $pallet->palletLines->count();
        $value    = $pallet->palletLines->sum(fn ($l) => $l->case_count * $l->quantity_per_case * $l->unit_cost);

        $lines = $pallet->palletLines->take(8)
            ->map(fn ($l) => "  - {$l->description} | cases={$l->case_count} × {$l->quantity_per_case} @ \${$l->unit_cost}")
            ->join("\n");

        return implode("\n", [
            "## Current Page — Pallet: {$pallet->reference}",
            '- Vendor: ' . ($pallet->vendor?->name ?? '—'),
            "- Status: {$pallet->status}",
            "- Lines: {$total} ({$received} received)",
            '- Est. pallet value: $' . number_format($value, 2),
            "### Lines\n{$lines}",
        ]);
    }

    private function palletsListContext(): string
    {
        $byStatus = Pallet::selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        return implode("\n", [
            '## Current Page — Pallets',
            '- By status: ' . $byStatus->map(fn ($c, $k) => "{$k}={$c}")->join(', '),
        ]);
    }

    private function receivingSessionContext(int $id): string
    {
        $session = ReceivingSession::with(['pallet.vendor'])->find($id);

        if (! $session) {
            return "## Current Page\nReceiving session #{$id} not found.";
        }

        return implode("\n", [
            "## Current Page — Receiving Session #{$id}",
            '- Pallet: ' . ($session->pallet?->reference ?? '—') . ' (' . ($session->pallet?->vendor?->name ?? 'unknown vendor') . ')',
            "- Status: {$session->status}",
            '- Lines: ' . ($session->total_lines ?? '—') . ' | Matched: ' . ($session->matched_lines ?? '—') . ' | Unmatched: ' . ($session->unmatched_lines ?? '—'),
        ]);
    }

    private function vendorContext(int $id): string
    {
        $vendor = Vendor::find($id);

        if (! $vendor) {
            return "## Current Page\nVendor #{$id} not found.";
        }

        $palletCount = Pallet::where('vendor_id', $id)->count();
        $openPallets = Pallet::where('vendor_id', $id)->where('status', 'pending')->count();

        return implode("\n", [
            "## Current Page — Vendor: {$vendor->name}",
            "- Status: {$vendor->status}",
            '- Email: ' . ($vendor->email ?? '—'),
            "- Total pallets: {$palletCount} ({$openPallets} open/pending)",
        ]);
    }

    private function streamerLogsContext(): string
    {
        $pending = \App\Models\StreamerLogEntry::whereNull('reviewed_at')->count();
        $recent  = \App\Models\StreamerLogEntry::with('streamer')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($e) => '  - ' . ($e->streamer?->name ?? '?') . ': total_due=$' . $e->total_due . ' [' . ($e->show?->show_date?->format('M j') ?? '—') . ']')
            ->join("\n");

        return implode("\n", [
            '## Current Page — Streamer Logs',
            "- Unreviewed entries: {$pending}",
            "### Recent\n{$recent}",
        ]);
    }

    private function ledgerContext(): string
    {
        $recentBalance = \App\Models\LedgerEntry::latest()->limit(1)->value('running_balance') ?? 0;
        $monthTotal    = \App\Models\LedgerEntry::whereMonth('created_at', now()->month)->sum('amount');

        return implode("\n", [
            '## Current Page — Ledger',
            '- Running balance: $' . number_format($recentBalance, 2),
            '- This month activity: $' . number_format($monthTotal, 2),
        ]);
    }
}
