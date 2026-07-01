<?php

namespace App\Filament\Pages;

use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class AiAssistant extends Page
{
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

    public static function canAccess(): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && AdminModules::isEnabled('ai');
    }

    public function getView(): string
    {
        return 'filament.pages.ai-assistant';
    }

    // ── State ─────────────────────────────────────────────────────────────────

    /** @var array<int, array{role:string, content:string, time:string, success?:bool, latency?:int}> */
    public array   $messages      = [];
    public string  $question      = '';
    public bool    $isLoading     = false;
    public bool    $ollamaOnline  = false;
    public string  $ollamaModel   = '';
    public string  $ollamaBaseUrl = '';

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
        return 'You are Vortex Assistant, an operations AI for Vortex Breaks, a Whatnot sports card break business. '
            . 'You help with inventory management, show reconciliation, streamer payouts, and operational questions. '
            . 'Be concise and practical. Use numbers and specifics when available. '
            . 'If you don\'t have access to real-time data, say so and suggest where to look in the app.';
    }

    // ── Quick-action prompts ──────────────────────────────────────────────────

    private function inventoryAnalysisPrompt(): string
    {
        $items = \App\Models\InventoryItem::with('stock')
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
        $items = \App\Models\InventoryItem::with('stock')
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->get(['id', 'name', 'category', 'reorder_level', 'unit_cost']);

        $low = $items->filter(function ($item) {
            return $item->stock->sum('quantity') <= $item->reorder_level;
        })->map(function ($item) {
            $qty = $item->stock->sum('quantity');
            return "- {$item->name}: qty={$qty}, reorder_level={$item->reorder_level}, unit_cost=\${$item->unit_cost}";
        })->join("\n");

        if (! $low) {
            return 'All my inventory items are above their reorder levels. Tell me that looks good and suggest I review reorder levels if any items are getting close.';
        }

        return "The following items are at or below their reorder level:\n{$low}\n\nSuggest reorder quantities and priorities based on cost and stock levels.";
    }

    private function movementAnalysisPrompt(): string
    {
        $cutoff    = now()->subDays(30);
        $movements = \App\Models\InventoryMovement::with('item')
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
        return rtrim(env('OLLAMA_BASE_URL', 'http://ollama:11434'), '/');
    }

    private function model(): string
    {
        return env('OLLAMA_MODEL', 'llama3.2:3b');
    }
}
