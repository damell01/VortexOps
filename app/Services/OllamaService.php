<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Streamer;
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

    /**
     * Given a list of sale item names/SKUs, suggest which inventory items they match.
     * Returns a map of sale item name → ['item_id', 'item_name', 'confidence', 'reasoning']
     */
    public function matchSalesToInventory(array $saleItems): array
    {
        $inventory = InventoryItem::where('is_active', true)
            ->select('id', 'sku', 'name', 'category')
            ->get()
            ->map(fn ($i) => ['id' => $i->id, 'sku' => $i->sku, 'name' => $i->name, 'category' => $i->category])
            ->values()
            ->all();

        $salesList = collect($saleItems)
            ->map(fn ($s, $i) => ($i + 1) . ". Name: \"{$s['item_name']}\"" . ($s['sku'] ? " | SKU: {$s['sku']}" : ''))
            ->implode("\n");

        $prompt = "You are a sports card inventory matcher. Match each sale item below to the best inventory item.\n\n"
            . "Sale items to match:\n{$salesList}\n\n"
            . "Inventory catalogue:\n" . json_encode($inventory, JSON_PRETTY_PRINT) . "\n\n"
            . "Respond ONLY with valid JSON: an array where each element is "
            . "{\"sale_item_name\": \"...\", \"matched_item_id\": 123, \"matched_item_name\": \"...\", \"confidence\": 0.95, \"reasoning\": \"brief reason\"}. "
            . "Use null for matched_item_id if no reasonable match exists. No markdown, no explanation outside the JSON array.";

        $log = $this->send($prompt, 'You are a precise JSON-only responder.', 'item_matching', []);

        if (!$log->success) {
            return [];
        }

        // Strip markdown code fences if the model wrapped it
        $raw  = trim(preg_replace('/^```(?:json)?\s*|\s*```$/s', '', $log->response ?? ''));
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Send a prompt expecting a JSON response; returns decoded array.
     * Non-blocking: returns [] on any failure or parse error.
     */
    public function json(string $prompt, string $system = '', array $context = []): array
    {
        try {
            $systemText = $system ?: 'You are a precise JSON-only responder. Respond only with valid JSON. No markdown, no explanation.';
            $log = $this->send($prompt, $systemText, 'json_request', $context);

            if (! $log->success) {
                return [];
            }

            $raw  = trim(preg_replace('/^```(?:json)?\s*|\s*```$/s', '', $log->response ?? ''));
            $data = json_decode($raw, true);

            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('OllamaService::json failed', ['error' => $e->getMessage()]);
            return [];
        }
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

    public function detectPageContext(string $path): array
    {
        try {
            // Inventory item detail
            if (preg_match('#admin/inventory-items/(\d+)#', $path, $m)) {
                $item = InventoryItem::with(['stock.location', 'movements' => fn ($q) => $q->latest()->limit(5)])->find($m[1]);
                if ($item) {
                    return [
                        'page_type'  => 'inventory_item',
                        'page_title' => $item->name,
                        'item' => [
                            'id'            => $item->id,
                            'name'          => $item->name,
                            'sku'           => $item->sku,
                            'category'      => $item->category,
                            'unit_cost'     => (float) $item->unit_cost,
                            'reorder_level' => (float) $item->reorder_level,
                            'total_qty'     => $item->totalQuantity(),
                            'is_low_stock'  => $item->isLowStock(),
                            'stock_by_location' => $item->stock->map(fn ($s) => [
                                'location' => $s->location?->name ?? 'Unknown',
                                'qty'      => (float) $s->quantity,
                            ])->all(),
                            'recent_movements' => $item->movements->map(fn ($mv) => [
                                'type'     => $mv->movement_type,
                                'qty'      => (float) $mv->quantity,
                                'date'     => $mv->created_at->toDateString(),
                                'reason'   => $mv->reason,
                            ])->all(),
                        ],
                    ];
                }
            }

            // Inventory items list
            if (str_contains($path, 'admin/inventory-items')) {
                return [
                    'page_type'  => 'inventory_items_list',
                    'page_title' => 'Inventory Items List',
                    'summary'    => $this->buildInventoryContext(),
                ];
            }

            // Location detail
            if (preg_match('#admin/inventory-locations/(\d+)#', $path, $m)) {
                $loc = InventoryLocation::with(['stock.item', 'streamer'])->find($m[1]);
                if ($loc) {
                    return [
                        'page_type'  => 'inventory_location',
                        'page_title' => $loc->name,
                        'location'   => [
                            'name'      => $loc->name,
                            'type'      => $loc->type,
                            'streamer'  => $loc->streamer?->name,
                            'sku_count' => $loc->stock->count(),
                            'stock'     => $loc->stock->map(fn ($s) => [
                                'item' => $s->item?->name,
                                'qty'  => (float) $s->quantity,
                            ])->all(),
                        ],
                    ];
                }
            }

            // Locations list
            if (str_contains($path, 'admin/inventory-locations')) {
                return [
                    'page_type'  => 'inventory_locations_list',
                    'page_title' => 'Inventory Locations',
                    'summary'    => $this->buildInventoryContext(),
                ];
            }

            // Streamer detail
            if (preg_match('#admin/streamers/(\d+)#', $path, $m)) {
                $streamer = Streamer::with(['locations.stock'])->find($m[1]);
                if ($streamer) {
                    return [
                        'page_type' => 'streamer',
                        'page_title' => $streamer->name,
                        'streamer'  => [
                            'name'               => $streamer->name,
                            'status'             => $streamer->status,
                            'payout_type'        => $streamer->payout_type,
                            'locations'          => $streamer->locations->map(fn ($l) => [
                                'name'      => $l->name,
                                'sku_count' => $l->stock->count(),
                                'total_qty' => $l->stock->sum('quantity'),
                            ])->all(),
                        ],
                    ];
                }
            }

            // Movement log
            if (str_contains($path, 'admin/inventory-movements')) {
                return [
                    'page_type'  => 'movement_log',
                    'page_title' => 'Movement Log',
                    'summary'    => $this->buildInventoryContext(),
                ];
            }

            // Stock levels
            if (str_contains($path, 'admin/inventory-stocks')) {
                return [
                    'page_type'  => 'stock_levels',
                    'page_title' => 'Stock Levels',
                    'summary'    => $this->buildInventoryContext(),
                ];
            }

            // Dashboard
            if ($path === 'admin' || $path === 'admin/') {
                return [
                    'page_type'  => 'dashboard',
                    'page_title' => 'Dashboard',
                    'summary'    => $this->buildInventoryContext(),
                ];
            }
        } catch (\Exception) {
        }

        return ['page_type' => 'general', 'page_title' => 'VortexOps'];
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
