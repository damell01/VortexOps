<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\ReviewItem;
use App\Models\ReviewSession;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLoan;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OllamaService
{
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        try {
            $this->baseUrl = rtrim(Setting::get('ollama_base_url', config('ollama.base_url', 'http://localhost:11434')), '/');
            $this->model   = Setting::get('ollama_model', config('ollama.model', 'llama3.2'));
            $this->timeout = (int) Setting::get('ollama_timeout', (string) config('ollama.timeout', 120));
        } catch (\Throwable) {
            $this->baseUrl = rtrim(config('ollama.base_url', 'http://localhost:11434'), '/');
            $this->model   = config('ollama.model', 'llama3.2');
            $this->timeout = config('ollama.timeout', 120);
        }
    }

    public function isAvailable(): bool
    {
<<<<<<< HEAD
        return Cache::remember($this->cacheKey('available'), now()->addSeconds(15), function (): bool {
            try {
                return Http::timeout(3)->get("{$this->baseUrl}/api/tags")->successful();
=======
        return Cache::remember("ollama_available:{$this->baseUrl}", 30, function () {
            try {
                return Http::timeout(1)->get("{$this->baseUrl}/api/tags")->successful();
>>>>>>> c978be893c3301dc9ddd4532010e33b3538a8ee3
            } catch (\Exception) {
                return false;
            }
        });
    }

    public function availableModels(): array
    {
<<<<<<< HEAD
        return Cache::remember($this->cacheKey('models'), now()->addSeconds(60), function (): array {
            try {
                $resp = Http::timeout(5)->get("{$this->baseUrl}/api/tags");

=======
        return Cache::remember("ollama_models:{$this->baseUrl}", 60, function () {
            try {
                $resp = Http::timeout(2)->get("{$this->baseUrl}/api/tags");
>>>>>>> c978be893c3301dc9ddd4532010e33b3538a8ee3
                return collect($resp->json('models', []))->pluck('name')->all();
            } catch (\Exception) {
                return [];
            }
        });
    }

    private function resolvePreferredModel(string $preferred): string
    {
        $models = $this->availableModels();

        if (empty($models)) {
            return $preferred;
        }

        if (in_array($preferred, $models, true)) {
            return $preferred;
        }

        $prefixMatch = collect($models)->first(fn (string $model) => str_starts_with($model, $preferred . ':'));

        return $prefixMatch ?: $preferred;
    }

    public function currentModel(): string
    {
        return $this->model;
    }

    public function currentBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function currentTimeout(): int
    {
        return $this->timeout;
    }

    public function askQuestion(string $question): AiLog
    {
        $context    = $this->buildInventoryContext();
        $systemText = $this->systemPrompt($context);

        return $this->send($question, $systemText, 'freeform_query', $context);
    }

    public function query(string $question, string $systemText, ?int $userId = null): AiLog
    {
        $log = $this->send($question, $systemText, 'freeform_query', []);

        if ($userId !== null && ! $log->user_id) {
            $log->update(['user_id' => $userId]);
        }

        return $log;
    }

    /**
     * Stream an Ollama response token-by-token, calling $onToken for each chunk.
     * Returns an AiLog with the full concatenated response.
     */
    public function streamQuestion(string $question, string $systemText, callable $onToken): AiLog
    {
        $start        = microtime(true);
        $fullResponse = '';
        $success      = true;
        $error        = null;
        $model        = $this->resolvePreferredModel($this->model);

        try {
            $response = Http::withOptions(['stream' => true])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/api/chat", [
                    'model'    => $model,
                    'stream'   => true,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemText],
                        ['role' => 'user',   'content' => $question],
                    ],
                ]);

            if ($response->successful()) {
                $body   = $response->toPsrResponse()->getBody();
                $buffer = '';

                while (! $body->eof()) {
                    $buffer .= $body->read(512);

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line   = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);

                        if (trim($line) === '') {
                            continue;
                        }

                        $data = json_decode($line, true);
                        if (isset($data['message']['content'])) {
                            $token         = $data['message']['content'];
                            $fullResponse .= $token;
                            $onToken($token);
                        }

                        if ($data['done'] ?? false) {
                            break 2;
                        }
                    }
                }
            } else {
                $success = false;
                $error   = "HTTP {$response->status()}: " . substr($response->body(), 0, 300);
            }
        } catch (\Exception $e) {
            $success = false;
            $error   = $e->getMessage();
        }

        return AiLog::create([
            'model'         => $model,
            'action_type'   => 'freeform_query',
            'prompt'        => $question,
            'response'      => $fullResponse,
            'context'       => [],
            'latency_ms'    => (int) ((microtime(true) - $start) * 1000),
            'success'       => $success,
            'error_message' => $error,
            'user_id'       => Auth::id(),
        ]);
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
        $start    = microtime(true);
        $success  = true;
        $response = '';
        $error    = null;
        $model    = $this->resolvePreferredModel($this->model);

        try {
            $result = $this->sendChatRequest($model, $systemText, $prompt);

            if ($result->successful()) {
                $response = $result->json('message.content', '');
            } elseif ($result->status() === 404 && str_contains($result->body(), 'model')) {
                $fallbackModel = $this->resolvePreferredModel($model);

                if ($fallbackModel !== $model) {
                    $model = $fallbackModel;

                    $retry = $this->sendChatRequest($model, $systemText, $prompt);

                    if ($retry->successful()) {
                        $response = $retry->json('message.content', '');
                    } else {
                        $success = false;
                        $error   = "HTTP {$retry->status()}: " . substr($retry->body(), 0, 300);
                    }
                } else {
                    $success = false;
                    $error   = "HTTP {$result->status()}: " . substr($result->body(), 0, 300);
                }
            } else {
                $success = false;
                $error   = "HTTP {$result->status()}: " . substr($result->body(), 0, 300);
            }
        } catch (\Throwable $e) {
            [$success, $response, $error, $model] = $this->handleChatException($e, $systemText, $prompt, $model);
        }

        return AiLog::create([
            'model'          => $model,
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

    private function ollamaRequest(?int $timeout = null): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(5)
            ->timeout($timeout ?? $this->timeout);
    }

    private function sendChatRequest(string $model, string $systemText, string $prompt): Response
    {
        return $this->ollamaRequest()->post("{$this->baseUrl}/api/chat", [
            'model' => $model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $systemText],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);
    }

    private function handleChatException(\Throwable $e, string $systemText, string $prompt, string $model): array
    {
        if (! $this->isTimeoutException($e)) {
            return [false, '', $e->getMessage(), $model];
        }

        $fallbackModel = $this->fastFallbackModel($model);

        if (! $fallbackModel || $fallbackModel === $model) {
            return [false, '', $e->getMessage(), $model];
        }

        try {
            $retry = $this->sendChatRequest($fallbackModel, $systemText, $prompt);

            if ($retry->successful()) {
                return [true, $retry->json('message.content', ''), null, $fallbackModel];
            }

            return [false, '', "HTTP {$retry->status()}: " . substr($retry->body(), 0, 300), $model];
        } catch (\Throwable $retryException) {
            return [false, '', $retryException->getMessage(), $model];
        }
    }

    private function isTimeoutException(\Throwable $e): bool
    {
        if (! $e instanceof ConnectionException) {
            return false;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'operation timed out');
    }

    private function fastFallbackModel(string $current): ?string
    {
        $models = $this->availableModels();

        if (empty($models)) {
            return null;
        }

        foreach (['qwen2.5:0.5b', 'qwen2.5:3b', 'llama3.2:3b', 'qwen2.5-coder:3b'] as $candidate) {
            $resolved = $this->resolvePreferredModel($candidate);

            if ($resolved !== $current && in_array($resolved, $models, true)) {
                return $resolved;
            }
        }

        return null;
    }

    private function cacheKey(string $suffix): string
    {
        return 'ollama:' . md5($this->baseUrl) . ':' . $suffix;
    }

    private function systemPrompt(array $context): string
    {
        return "You are VortexOps AI, an inventory assistant for Vortex Breaks — a sports card break business that streams on Whatnot. "
            . "You have access to the current inventory snapshot below. Answer questions concisely and accurately. "
            . "Use plain text or bullet points. Do not use markdown headers.\n\n"
            . "Current inventory snapshot:\n"
            . json_encode($context, JSON_PRETTY_PRINT);
    }

    public function buildProjectContext(): array
    {
        $ctx = [];

        // Inventory
        try {
            $ctx['inventory'] = $this->buildInventoryContext();
        } catch (\Exception) {
            $ctx['inventory'] = ['error' => 'unavailable'];
        }

        // Streamers
        try {
            $ctx['streamers'] = Streamer::with(['loans' => fn ($q) => $q->where('status', 'active')])
                ->get()
                ->map(fn ($s) => [
                    'id'                  => $s->id,
                    'name'                => $s->name,
                    'status'              => $s->status,
                    'payout_type'         => $s->payout_type,
                    'payout_percentage'   => (float) ($s->payout_percentage ?? 0),
                    'package_rate'        => (float) ($s->package_rate ?? 0),
                    'hourly_rate'         => (float) ($s->hourly_rate ?? 0),
                    'custom_formula'      => $s->custom_payout_formula,
                    'owner_fee_type'      => $s->owner_fee_type,
                    'owner_fee_value'     => (float) ($s->owner_fee_value ?? 0),
                    'outstanding_loans'   => $s->loans->sum('remaining_balance'),
                ])->all();
        } catch (\Exception) {
            $ctx['streamers'] = ['error' => 'unavailable'];
        }

        // Recent shows
        try {
            $ctx['recent_shows'] = Show::with('streamers:id,name')
                ->latest('show_date')
                ->limit(10)
                ->get()
                ->map(fn ($s) => [
                    'id'            => $s->id,
                    'title'         => $s->title,
                    'show_date'     => $s->show_date?->toDateString(),
                    'status'        => $s->status,
                    'gross_revenue' => (float) ($s->gross_revenue ?? 0),
                    'units_sold'    => (int) ($s->units_sold ?? 0),
                    'streamers'     => $s->streamers->pluck('name')->all(),
                ])->all();

            $ctx['shows_summary'] = [
                'total'              => Show::count(),
                'pending_review'     => Show::where('status', 'pending_review')->count(),
                'pending_approval'   => Show::where('status', 'pending_approval')->count(),
                'gross_revenue_30d'  => (float) Show::where('show_date', '>=', now()->subDays(30))->sum('gross_revenue'),
            ];
        } catch (\Exception) {
            $ctx['shows_summary'] = ['error' => 'unavailable'];
        }

        // Payouts
        try {
            $ctx['payouts_summary'] = [
                'total_draft'          => Payout::where('status', 'draft')->count(),
                'total_approved'       => Payout::where('status', 'approved')->count(),
                'total_paid_30d'       => Payout::where('status', 'paid')->where('updated_at', '>=', now()->subDays(30))->count(),
                'amount_paid_30d'      => (float) Payout::where('status', 'paid')->where('updated_at', '>=', now()->subDays(30))->sum('calculated_payout'),
                'current_draft_amount' => (float) Payout::where('status', 'draft')->sum('calculated_payout'),
            ];

            $ctx['current_pay_run'] = WeeklyPayoutBatch::with('payouts.streamer')
                ->latest()
                ->first()?->only(['week_start', 'week_end', 'status', 'total_payout']);
        } catch (\Exception) {
            $ctx['payouts_summary'] = ['error' => 'unavailable'];
        }

        // Loans
        try {
            $ctx['loans'] = StreamerLoan::with('streamer:id,name')
                ->where('status', 'active')
                ->get()
                ->map(fn ($l) => [
                    'streamer'           => $l->streamer?->name,
                    'original_amount'    => (float) $l->amount,
                    'remaining_balance'  => (float) $l->remaining_balance,
                    'issued_date'        => $l->issued_date?->toDateString(),
                ])->all();
        } catch (\Exception) {
            $ctx['loans'] = ['error' => 'unavailable'];
        }

        // Review items
        try {
            $ctx['review_summary'] = [
                'open_items'        => ReviewItem::where('status', 'open')->count(),
                'in_progress_items' => ReviewItem::where('status', 'in_progress')->count(),
                'active_sessions'   => ReviewSession::where('status', '!=', 'closed')->count(),
            ];
        } catch (\Exception) {
            $ctx['review_summary'] = ['error' => 'unavailable'];
        }

        $ctx['snapshot_at'] = now()->toISOString();

        return $ctx;
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

            if (str_contains($path, 'admin/inventory-items')) {
                return ['page_type' => 'inventory_items_list', 'page_title' => 'Inventory Items'];
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

            if (str_contains($path, 'admin/inventory-locations')) {
                return ['page_type' => 'inventory_locations_list', 'page_title' => 'Inventory Locations'];
            }

            if (str_contains($path, 'admin/inventory-movements')) {
                return ['page_type' => 'movement_log', 'page_title' => 'Movement Log'];
            }

            if (str_contains($path, 'admin/inventory-stocks')) {
                return ['page_type' => 'stock_levels', 'page_title' => 'Stock Levels'];
            }

            // Streamer detail
            if (preg_match('#admin/streamers/(\d+)#', $path, $m)) {
                $streamer = Streamer::with(['locations.stock', 'loans' => fn ($q) => $q->where('status', 'active'), 'payouts' => fn ($q) => $q->latest()->limit(5)])->find($m[1]);
                if ($streamer) {
                    return [
                        'page_type'  => 'streamer',
                        'page_title' => $streamer->name,
                        'streamer'   => [
                            'name'               => $streamer->name,
                            'status'             => $streamer->status,
                            'payout_type'        => $streamer->payout_type,
                            'payout_percentage'  => (float) ($streamer->payout_percentage ?? 0),
                            'owner_fee_type'     => $streamer->owner_fee_type,
                            'owner_fee_value'    => (float) ($streamer->owner_fee_value ?? 0),
                            'outstanding_loans'  => $streamer->loans->sum('remaining_balance'),
                            'recent_payouts'     => $streamer->payouts->map(fn ($p) => [
                                'calculated_payout' => (float) $p->calculated_payout,
                                'status'            => $p->status,
                                'date'              => $p->created_at->toDateString(),
                            ])->all(),
                            'locations'          => $streamer->locations->map(fn ($l) => [
                                'name'      => $l->name,
                                'sku_count' => $l->stock->count(),
                            ])->all(),
                        ],
                    ];
                }
            }

            if (str_contains($path, 'admin/streamers')) {
                return ['page_type' => 'streamers_list', 'page_title' => 'Streamers'];
            }

            // Show detail
            if (preg_match('#admin/shows/(\d+)#', $path, $m)) {
                $show = Show::with(['streamers:id,name', 'payouts.streamer:id,name'])->find($m[1]);
                if ($show) {
                    return [
                        'page_type'  => 'show',
                        'page_title' => $show->title ?? 'Show #' . $show->id,
                        'show'       => [
                            'id'            => $show->id,
                            'title'         => $show->title,
                            'show_date'     => $show->show_date?->toDateString(),
                            'status'        => $show->status,
                            'gross_revenue' => (float) ($show->gross_revenue ?? 0),
                            'units_sold'    => (int) ($show->units_sold ?? 0),
                            'streamers'     => $show->streamers->pluck('name')->all(),
                            'payouts'       => $show->payouts->map(fn ($p) => [
                                'streamer'          => $p->streamer?->name,
                                'calculated_payout' => (float) $p->calculated_payout,
                                'status'            => $p->status,
                            ])->all(),
                        ],
                    ];
                }
            }

            if (str_contains($path, 'admin/shows')) {
                return ['page_type' => 'shows_list', 'page_title' => 'Shows'];
            }

            // Payout detail
            if (preg_match('#admin/payouts/(\d+)#', $path, $m)) {
                $payout = Payout::with(['show', 'streamer', 'batch'])->find($m[1]);
                if ($payout) {
                    return [
                        'page_type'  => 'payout',
                        'page_title' => 'Payout for ' . ($payout->streamer?->name ?? 'Unknown'),
                        'payout'     => [
                            'streamer'                => $payout->streamer?->name,
                            'show'                    => $payout->show?->title,
                            'payout_type'             => $payout->payout_type,
                            'gross_show_revenue'      => (float) $payout->gross_show_revenue,
                            'owner_fee_deducted'      => (float) ($payout->owner_fee_deducted ?? 0),
                            'loan_repayment_deducted' => (float) ($payout->loan_repayment_deducted ?? 0),
                            'tips_included'           => (float) $payout->tips_included,
                            'calculated_payout'       => (float) $payout->calculated_payout,
                            'status'                  => $payout->status,
                        ],
                    ];
                }
            }

            if (str_contains($path, 'admin/payouts')) {
                return ['page_type' => 'payouts_list', 'page_title' => 'Payouts'];
            }

            // Pay runs
            if (preg_match('#admin/weekly-payout-batches/(\d+)#', $path, $m)) {
                $batch = WeeklyPayoutBatch::with('payouts.streamer')->find($m[1]);
                if ($batch) {
                    return [
                        'page_type'  => 'pay_run',
                        'page_title' => 'Pay Run ' . $batch->week_start?->format('M j'),
                        'pay_run'    => [
                            'week_start'    => $batch->week_start?->toDateString(),
                            'week_end'      => $batch->week_end?->toDateString(),
                            'status'        => $batch->status,
                            'total_payout'  => (float) $batch->total_payout,
                            'streamer_count' => $batch->payouts->count(),
                            'payouts'       => $batch->payouts->map(fn ($p) => [
                                'streamer'          => $p->streamer?->name,
                                'calculated_payout' => (float) $p->calculated_payout,
                            ])->all(),
                        ],
                    ];
                }
            }

            if (str_contains($path, 'admin/weekly-payout-batches')) {
                return ['page_type' => 'pay_runs_list', 'page_title' => 'Pay Runs'];
            }

            // Review items
            if (str_contains($path, 'admin/review-items') || str_contains($path, 'admin/review-sessions')) {
                return ['page_type' => 'review', 'page_title' => 'Review Items'];
            }

            // Dashboard
            if ($path === 'admin' || $path === 'admin/') {
                return ['page_type' => 'dashboard', 'page_title' => 'Dashboard'];
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
