<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class OllamaService
{
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('ollama.base_url', 'http://localhost:11434'), '/');
        $this->model   = config('ollama.model', 'llama3.2');
        $this->timeout = config('ollama.timeout', 60);
    }

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(3)->get("{$this->baseUrl}/api/tags")->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function availableModels(): array
    {
        try {
            $resp = Http::timeout(5)->get("{$this->baseUrl}/api/tags");
            return collect($resp->json('models', []))->pluck('name')->all();
        } catch (\Exception) {
            return [];
        }
    }

    public function askQuestion(string $question): AiLog
    {
        $context    = $this->buildInventoryContext();
        $systemText = $this->systemPrompt($context);

        return $this->send($question, $systemText, 'freeform_query', $context);
    }

    public function inventoryAnalysis(): AiLog
    {
        $context    = $this->buildInventoryContext();
        $systemText = $this->systemPrompt($context);
        $prompt     = "Provide a brief inventory health summary with: (1) key concerns, (2) what needs immediate attention, (3) one actionable recommendation. Use bullet points. Be concise.";

        return $this->send($prompt, $systemText, 'inventory_analysis', $context);
    }

    public function reorderSuggestions(): AiLog
    {
        $context    = $this->buildInventoryContext();
        $systemText = $this->systemPrompt($context);

        $lowStock = collect($context['low_stock_items'] ?? [])
            ->map(fn ($i) => "- {$i['name']} (qty: {$i['quantity']}, reorder at: {$i['reorder_level']})")
            ->implode("\n");

        $prompt = "Low-stock items:\n{$lowStock}\n\nSuggest reorder priorities and estimated quantities based on typical sports card break business demand. Keep it brief and actionable.";

        return $this->send($prompt, $systemText, 'reorder_suggestion', $context);
    }

    public function movementAnalysis(): AiLog
    {
        $context    = $this->buildInventoryContext();
        $systemText = $this->systemPrompt($context);
        $prompt     = "Analyse the recent movement patterns shown in the context. Identify any anomalies, high-velocity items, or locations with unusual activity. Keep it brief.";

        return $this->send($prompt, $systemText, 'movement_analysis', $context);
    }

    private function send(string $prompt, string $systemText, string $actionType, array $context): AiLog
    {
        $start   = microtime(true);
        $success = true;
        $response = '';
        $error    = null;

        try {
            $result = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/chat", [
                    'model'  => $this->model,
                    'stream' => false,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemText],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                ]);

            if ($result->successful()) {
                $response = $result->json('message.content', '');
            } else {
                $success = false;
                $error   = "HTTP {$result->status()}: " . substr($result->body(), 0, 300);
            }
        } catch (\Exception $e) {
            $success = false;
            $error   = $e->getMessage();
        }

        return AiLog::create([
            'model'          => $this->model,
            'action_type'    => $actionType,
            'prompt'         => $prompt,
            'response'       => $response,
            'context'        => $context,
            'latency_ms'     => (int) ((microtime(true) - $start) * 1000),
            'success'        => $success,
            'error_message'  => $error,
            'user_id'        => Auth::id(),
        ]);
    }

    private function systemPrompt(array $context): string
    {
        return "You are VortexOps AI, an inventory assistant for Vortex Breaks — a sports card break business that streams on Whatnot. "
            . "You have access to the current inventory snapshot below. Answer questions concisely and accurately. "
            . "Use plain text or bullet points. Do not use markdown headers.\n\n"
            . "Current inventory snapshot:\n"
            . json_encode($context, JSON_PRETTY_PRINT);
    }

    public function buildInventoryContext(): array
    {
        try {
            $recentMovements = InventoryMovement::with(['item', 'fromLocation', 'toLocation'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($m) => [
                    'type'          => $m->movement_type,
                    'item'          => $m->item?->name,
                    'from_location' => $m->fromLocation?->name,
                    'to_location'   => $m->toLocation?->name,
                    'quantity'      => (float) $m->quantity,
                    'date'          => $m->created_at->toDateString(),
                ])
                ->all();

            $lowStockItems = InventoryItem::query()
                ->select('id', 'name', 'reorder_level', 'category')
                ->selectRaw('(SELECT COALESCE(SUM(quantity),0) FROM inventory_stock WHERE inventory_item_id = inventory_items.id) as quantity')
                ->where('is_active', true)
                ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM inventory_stock WHERE inventory_item_id = inventory_items.id) <= reorder_level')
                ->get()
                ->map(fn ($i) => [
                    'name'          => $i->name,
                    'category'      => $i->category,
                    'quantity'      => (float) $i->quantity,
                    'reorder_level' => (float) $i->reorder_level,
                ])
                ->all();

            return [
                'snapshot_at'             => now()->toISOString(),
                'total_active_items'      => InventoryItem::where('is_active', true)->count(),
                'total_active_locations'  => InventoryLocation::where('status', 'active')->count(),
                'total_units'             => (float) InventoryStock::sum('quantity'),
                'movements_last_7_days'   => InventoryMovement::where('created_at', '>=', now()->subDays(7))->count(),
                'low_stock_items'         => $lowStockItems,
                'items_by_category'       => InventoryItem::selectRaw('category, count(*) as count')
                    ->where('is_active', true)
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray(),
                'recent_movements'        => $recentMovements,
            ];
        } catch (\Exception) {
            return ['snapshot_at' => now()->toISOString(), 'error' => 'Could not load inventory context'];
        }
    }
}
