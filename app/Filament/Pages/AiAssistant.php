<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Pallet;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiAssistant extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'ai';
    protected static string $featureSlug = 'ai_assistant';
    protected static ?string $title = 'AI Assistant';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'AI';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-sparkles';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function getView(): string
    {
        return 'filament.pages.ai-assistant';
    }

    // ── State ─────────────────────────────────────────────────────────────────

    /** @var array<int, array{role:string, content:string, time:string, success?:bool, latency?:int}> */
    public array  $messages      = [];
    public string $question      = '';
    public bool   $isLoading     = false;
    public bool   $ollamaOnline  = false;
    public string $ollamaModel   = '';
    public string $ollamaBaseUrl = '';

    /** @var array<int, string> */
    public array $availableModels = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->ollamaBaseUrl = $this->baseUrl();
        $this->ollamaModel   = $this->model();
        $this->checkOllama();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function sendMessage(): void
    {
        $text = trim($this->question);
        if ($text === '' || $this->isLoading) {
            return;
        }

        $this->question   = '';
        $this->isLoading  = true;
        $this->messages[] = ['role' => 'user', 'content' => $text, 'time' => now()->format('g:i A')];

        $this->dispatch('scroll-to-bottom');
        [$content, $latency, $ok] = $this->chat($text);

        $this->messages[] = ['role' => 'assistant', 'content' => $content, 'time' => now()->format('g:i A'), 'success' => $ok, 'latency' => $latency];
        $this->isLoading  = false;
        $this->dispatch('scroll-to-bottom');
    }

    public function runQuickAction(string $type): void
    {
        if ($this->isLoading) {
            return;
        }

        $prompt = match ($type) {
            'inventory_analysis'  => $this->inventoryAnalysisPrompt(),
            'reorder_suggestions' => $this->reorderSuggestionsPrompt(),
            'movement_analysis'   => $this->movementAnalysisPrompt(),
            'shows_overview'      => $this->showsOverviewPrompt(),
            'revenue_summary'     => $this->revenueSummaryPrompt(),
            'streamer_summary'    => $this->streamerSummaryPrompt(),
            'payout_summary'      => $this->payoutSummaryPrompt(),
            'pallet_status'       => $this->palletStatusPrompt(),
            'operations_briefing' => $this->operationsBriefingPrompt(),
            default               => null,
        };

        if (! $prompt) {
            return;
        }

        $this->question = $prompt;
        $this->sendMessage();
    }

    public function clearChat(): void
    {
        $this->messages = [];
    }

    // ── Ollama helpers ────────────────────────────────────────────────────────

    private function checkOllama(): void
    {
        try {
            $response = Http::timeout(3)->get("{$this->ollamaBaseUrl}/api/tags");
            if ($response->successful()) {
                $this->ollamaOnline    = true;
                $this->availableModels = collect($response->json('models', []))->pluck('name')->all();
            }
        } catch (\Throwable) {
            $this->ollamaOnline = false;
        }
    }

    private function chat(string $userMessage): array
    {
        if (! $this->ollamaOnline) {
            return ['Ollama is offline. Start it with `ollama serve` or enable the ollama Docker service.', 0, false];
        }

        $start = microtime(true);

        try {
            $response = Http::timeout(120)->post("{$this->ollamaBaseUrl}/api/chat", [
                'model'    => $this->ollamaModel,
                'messages' => $this->buildHistory($userMessage),
                'stream'   => false,
            ]);

            $latency = (int) ((microtime(true) - $start) * 1000);

            if (! $response->successful()) {
                return ["Ollama returned HTTP {$response->status()}.", $latency, false];
            }

            $content = $response->json('message.content', '');
            return [$content ?: '(empty response)', $latency, true];
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $start) * 1000);
            return ["Error: {$e->getMessage()}", $latency, false];
        }
    }

    /** @return array<int, array{role:string, content:string}> */
    private function buildHistory(string $userMessage): array
    {
        $history = [['role' => 'system', 'content' => $this->systemPrompt()]];

        foreach ($this->messages as $msg) {
            $history[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $history[] = ['role' => 'user', 'content' => $userMessage];
        return $history;
    }

    private function systemPrompt(): string
    {
        $snapshot = $this->buildDataSnapshot();

        return <<<PROMPT
You are Vortex Assistant, an operations AI for Vortex Breaks — a Whatnot sports card break business.
You have access to real-time data from the operations platform. Use the snapshot below to answer questions accurately.
Be concise, practical, and specific. Use dollar amounts and counts directly from the data.

=== LIVE DATA SNAPSHOT (as of {$snapshot['as_of']}) ===

SHOWS (last 30 days):
{$snapshot['shows']}

REVENUE (last 30 days):
{$snapshot['revenue']}

STREAMERS:
{$snapshot['streamers']}

PAYOUTS:
{$snapshot['payouts']}

INVENTORY:
{$snapshot['inventory']}

PALLETS / RECEIVING:
{$snapshot['pallets']}

=== END SNAPSHOT ===

When asked about shows, streamers, payouts, inventory, or receiving — answer from the snapshot above.
If data isn't in the snapshot and you can't calculate it, say so and point to the relevant section in the app.
PROMPT;
    }

    // ── Live data snapshot ────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function buildDataSnapshot(): array
    {
        return Cache::remember('ai_snapshot_' . auth()->id(), 300, function () {
            return $this->fetchDataSnapshot();
        });
    }

    /** @return array<string, string> */
    private function fetchDataSnapshot(): array
    {
        $cutoff = now()->subDays(30);

        // Shows
        $shows        = Show::where('show_date', '>=', $cutoff)->orderByDesc('show_date')->get();
        $showStatuses = $shows->groupBy('status')->map->count();
        $showLines    = $shows->take(10)->map(fn ($s) =>
            "  {$s->show_date->format('M j')}: {$s->title} | gross=\${$s->gross_revenue} units={$s->units_sold} status={$s->status}"
        )->join("\n");

        $showsSummary = "Total: {$shows->count()} shows | "
            . $showStatuses->map(fn ($c, $k) => "{$k}={$c}")->join(', ')
            . "\nRecent:\n" . ($showLines ?: '  (none)');

        // Revenue
        $totalGross   = $shows->sum('gross_revenue');
        $totalNet     = $shows->sum('whatnot_net');
        $totalTips    = $shows->sum('tips');
        $totalUnits   = $shows->sum('units_sold');
        $avgGross     = $shows->count() ? round($totalGross / $shows->count(), 2) : 0;

        $revenueSummary = "Gross revenue: \${$totalGross} | Whatnot net: \${$totalNet} | Tips: \${$totalTips}\n"
            . "Units sold: {$totalUnits} | Shows: {$shows->count()} | Avg gross/show: \${$avgGross}";

        // Streamers
        $streamers     = Streamer::where('status', 'active')->get(['id', 'name', 'payout_type', 'total_earnings_due', 'total_earnings_paid']);
        $streamerLines = $streamers->map(fn ($s) => "  {$s->name} ({$s->payout_type}) | due=\${$s->total_earnings_due} paid=\${$s->total_earnings_paid}")->join("\n");
        $streamerSummary = "Active streamers: {$streamers->count()}\n" . ($streamerLines ?: '  (none)');

        // Payouts
        $pendingPayouts   = Payout::where('status', 'pending')->count();
        $approvedPayouts  = Payout::where('status', 'approved')->sum('calculated_payout');
        $recentBatch      = WeeklyPayoutBatch::latest('week_start')->first();
        $batchLine        = $recentBatch
            ? "Latest batch: {$recentBatch->week_start->format('M j')}–{$recentBatch->week_end->format('M j')} | total=\${$recentBatch->total_payout} status={$recentBatch->status}"
            : 'No batches yet';

        $payoutSummary = "Pending payouts: {$pendingPayouts} | Approved (unpaid): \${$approvedPayouts}\n{$batchLine}";

        // Inventory
        $itemCount    = InventoryItem::where('is_active', true)->count();
        $lowStock     = InventoryItem::with('stock')
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->get()
            ->filter(fn ($i) => $i->stock->sum('quantity') <= $i->reorder_level);
        $totalValue   = InventoryItem::with('stock')
            ->where('is_active', true)
            ->get()
            ->sum(fn ($i) => $i->stock->sum('quantity') * $i->average_cost);

        $lowStockLines = $lowStock->take(5)->map(fn ($i) => "  {$i->name}: qty={$i->stock->sum('quantity')} reorder={$i->reorder_level}")->join("\n");
        $inventorySummary = "Active items: {$itemCount} | Est. total value: \$" . number_format($totalValue, 2)
            . "\nLow stock ({$lowStock->count()} items):\n" . ($lowStockLines ?: '  (none)');

        // Pallets
        $palletsByStatus = Pallet::selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $pendingPallets  = Pallet::where('status', 'pending')->with('vendor')->latest()->take(5)->get();
        $palletLines     = $pendingPallets->map(fn ($p) => "  {$p->reference} ({$p->vendor?->name})")->join("\n");
        $palletSummary   = $palletsByStatus->map(fn ($c, $k) => "{$k}={$c}")->join(', ')
            . "\nPending:\n" . ($palletLines ?: '  (none)');

        return [
            'as_of'      => now()->format('Y-m-d H:i'),
            'shows'      => $showsSummary,
            'revenue'    => $revenueSummary,
            'streamers'  => $streamerSummary,
            'payouts'    => $payoutSummary,
            'inventory'  => $inventorySummary,
            'pallets'    => $palletSummary,
        ];
    }

    // ── Quick-action prompts ──────────────────────────────────────────────────

    private function operationsBriefingPrompt(): string
    {
        return 'Give me a complete operations briefing for today. Cover: '
            . '(1) show pipeline and any pending reconciliation, '
            . '(2) streamer payout status, '
            . '(3) inventory health and low-stock alerts, '
            . '(4) pallets awaiting receiving, '
            . '(5) anything that needs attention right now. '
            . 'Be direct and use the data in your context.';
    }

    private function showsOverviewPrompt(): string
    {
        $shows = Show::where('show_date', '>=', now()->subDays(30))
            ->orderByDesc('show_date')
            ->get(['title', 'show_date', 'gross_revenue', 'whatnot_net', 'units_sold', 'status']);

        $lines = $shows->map(fn ($s) =>
            "- {$s->show_date->format('M j')}: {$s->title} | gross=\${$s->gross_revenue} net=\${$s->whatnot_net} units={$s->units_sold} [{$s->status}]"
        )->join("\n");

        $pending = $shows->where('status', 'pending_review')->count();
        $reconciled = $shows->where('status', 'reconciled')->count();

        return "Here are my shows from the last 30 days:\n{$lines}\n\n"
            . "Pending review: {$pending}, Reconciled: {$reconciled}.\n"
            . 'Give me a show pipeline analysis — which shows need attention, any revenue trends, and what should I prioritize?';
    }

    private function revenueSummaryPrompt(): string
    {
        $shows = Show::where('show_date', '>=', now()->subDays(90))
            ->orderByDesc('show_date')
            ->get(['show_date', 'gross_revenue', 'whatnot_net', 'tips', 'units_sold']);

        $byWeek = $shows->groupBy(fn ($s) => $s->show_date->startOfWeek()->format('M j'))
            ->map(fn ($g) => [
                'shows'   => $g->count(),
                'gross'   => $g->sum('gross_revenue'),
                'net'     => $g->sum('whatnot_net'),
                'units'   => $g->sum('units_sold'),
            ]);

        $lines = $byWeek->map(fn ($w, $week) =>
            "- Week of {$week}: {$w['shows']} shows | gross=\${$w['gross']} net=\${$w['net']} units={$w['units']}"
        )->join("\n");

        return "Here is my weekly revenue breakdown for the last 90 days:\n{$lines}\n\n"
            . 'Identify revenue trends, best and worst weeks, and give me a forecast or observation on where the business is heading.';
    }

    private function streamerSummaryPrompt(): string
    {
        $streamers = Streamer::where('status', 'active')
            ->with(['payouts' => fn ($q) => $q->where('status', 'pending')])
            ->get(['id', 'name', 'payout_type', 'total_earnings_due', 'total_earnings_paid']);

        $lines = $streamers->map(fn ($s) => sprintf(
            '- %s (%s): due=$%.2f paid=$%.2f pending_payouts=%d',
            $s->name, $s->payout_type,
            $s->total_earnings_due, $s->total_earnings_paid,
            $s->payouts->count()
        ))->join("\n");

        $totalOwed = $streamers->sum(fn ($s) => max(0, $s->total_earnings_due - $s->total_earnings_paid));

        return "Here is my active streamer roster:\n{$lines}\n\n"
            . "Total owed across all streamers: \${$totalOwed}.\n"
            . 'Give me a payout status summary — who has the most outstanding balance, any anomalies, and should anyone be flagged?';
    }

    private function payoutSummaryPrompt(): string
    {
        $batches = WeeklyPayoutBatch::with('payouts.streamer')
            ->latest('week_start')
            ->take(8)
            ->get();

        $batchLines = $batches->map(fn ($b) =>
            "- {$b->week_start->format('M j')}–{$b->week_end->format('M j')}: \${$b->total_payout} [{$b->status}] ({$b->payouts->count()} payouts)"
        )->join("\n");

        $pending = Payout::where('status', 'pending')->count();
        $pendingTotal = Payout::where('status', 'pending')->sum('calculated_payout');

        return "Recent weekly payout batches:\n{$batchLines}\n\n"
            . "Unprocessed pending payouts: {$pending} totaling \${$pendingTotal}.\n"
            . 'Summarize payout health — is anything overdue, what should be finalized next, and are the amounts reasonable?';
    }

    private function palletStatusPrompt(): string
    {
        $pallets = Pallet::with(['vendor', 'palletLines'])
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        $lines = $pallets->map(fn ($p) => sprintf(
            '- %s | vendor=%s | lines=%d | status=%s | created=%s',
            $p->reference,
            $p->vendor?->name ?? 'unknown',
            $p->palletLines->count(),
            $p->status,
            $p->created_at->format('M j')
        ))->join("\n");

        return "Here are my recent pallets (receiving):\n{$lines}\n\n"
            . 'Which pallets are stuck or pending too long? Any receiving backlog I should address?';
    }

    private function inventoryAnalysisPrompt(): string
    {
        $items = InventoryItem::with('stock')
            ->where('is_active', true)
            ->orderByDesc('total_units_received')
            ->limit(20)
            ->get(['id', 'name', 'category', 'average_cost', 'reorder_level', 'total_units_received']);

        $summary = $items->map(function ($item) {
            $qty = $item->stock->sum('quantity');
            $low = $item->reorder_level && $qty <= $item->reorder_level ? ' [LOW]' : '';
            return "- {$item->name} ({$item->category}): qty={$qty}, avg_cost=\${$item->average_cost}{$low}";
        })->join("\n");

        return "Here is my top-20 inventory by receipts:\n{$summary}\n\nGive me a quick health analysis — any concerns, low-stock alerts, or observations.";
    }

    private function reorderSuggestionsPrompt(): string
    {
        $items = InventoryItem::with('stock')
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->get(['id', 'name', 'category', 'reorder_level', 'unit_cost']);

        $low = $items->filter(fn ($item) => $item->stock->sum('quantity') <= $item->reorder_level)
            ->map(fn ($item) => "- {$item->name}: qty={$item->stock->sum('quantity')}, reorder_level={$item->reorder_level}, unit_cost=\${$item->unit_cost}")
            ->join("\n");

        if (! $low) {
            return 'All my inventory items are above their reorder levels. Tell me that looks good and suggest I review reorder levels if any items are getting close.';
        }

        return "The following items are at or below their reorder level:\n{$low}\n\nSuggest reorder quantities and priorities based on cost and stock levels.";
    }

    private function movementAnalysisPrompt(): string
    {
        $cutoff    = now()->subDays(30);
        $movements = InventoryMovement::with('item')
            ->where('created_at', '>=', $cutoff)
            ->selectRaw('movement_type, COUNT(*) as count, SUM(quantity) as total_qty')
            ->groupBy('movement_type')
            ->get();

        $summary = $movements->map(fn ($m) => "- {$m->movement_type}: {$m->count} movements, {$m->total_qty} units")->join("\n");

        return "Here are my inventory movements for the last 30 days:\n{$summary}\n\nWhat patterns do you see? Anything to flag?";
    }

    // ── Config ────────────────────────────────────────────────────────────────

    private function baseUrl(): string
    {
        return rtrim(config('services.ollama.url', 'http://ollama:11434'), '/');
    }

    private function model(): string
    {
        return config('services.ollama.model', 'llama3.2:3b');
    }
}
