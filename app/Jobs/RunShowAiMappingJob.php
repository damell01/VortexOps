<?php

namespace App\Jobs;

use App\Models\AiTask;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\User;
use App\Notifications\AiTaskCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunShowAiMappingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(
        private readonly int $showId,
        private readonly int $aiTaskId,
    ) {}

    public function handle(): void
    {
        $task = AiTask::findOrFail($this->aiTaskId);
        $show = Show::with(['streamers', 'deductionRequests.lines'])->findOrFail($this->showId);

        $task->markProcessing();
        $show->update(['status' => 'mapping']);

        try {
            $lines   = $this->extractRawLines($show);
            $items   = InventoryItem::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'category', 'average_cost', 'unit_cost']);
            $mapped  = $this->mapLinesWithAi($lines, $items);
            $summary = $this->persistMappings($show, $mapped);

            $show->update(['status' => 'pending_approval']);
            $task->markCompleted($summary);
        } catch (\Throwable $e) {
            Log::error('RunShowAiMappingJob failed', ['show_id' => $this->showId, 'error' => $e->getMessage()]);
            $show->update(['status' => 'pending_review']);
            $task->markFailed($e->getMessage());
        }

        $this->notify($task);
    }

    private function extractRawLines(Show $show): array
    {
        $lines = [];

        // Use raw_import_payload if present (Whatnot import)
        $payload = $show->raw_import_payload ?? [];
        if (! empty($payload['items'])) {
            foreach ($payload['items'] as $item) {
                $lines[] = [
                    'description' => $item['title'] ?? $item['name'] ?? $item['description'] ?? '',
                    'quantity'    => (float) ($item['quantity'] ?? $item['qty'] ?? 1),
                    'source'      => 'import',
                ];
            }
        }

        // Fall back to existing DeductionRequest lines that have raw_description but no inventory_item_id
        if (empty($lines)) {
            foreach ($show->deductionRequests as $dr) {
                foreach ($dr->lines->where('inventory_item_id', null) as $line) {
                    if ($line->raw_description) {
                        $lines[] = [
                            'description' => $line->raw_description,
                            'quantity'    => (float) $line->quantity_suggested,
                            'source'      => 'existing_line',
                            'line_id'     => $line->id,
                        ];
                    }
                }
            }
        }

        return $lines;
    }

    private function mapLinesWithAi(array $lines, $items): array
    {
        $itemList = $items->map(fn ($i) => "[{$i->id}] {$i->name}" . ($i->sku ? " (SKU: {$i->sku})" : '') . ($i->category ? " [{$i->category}]" : ''))->join("\n");
        $results  = [];

        foreach ($lines as $line) {
            if (empty($line['description'])) {
                continue;
            }

            $prompt = <<<PROMPT
You are matching a sold item description to an inventory catalogue for a sports card break business.

Inventory items:
{$itemList}

Sold item: "{$line['description']}"

Reply with JSON only, no explanation:
{"item_id": <integer or null>, "confidence": "high|medium|low", "reason": "<one sentence>"}

If no match is reasonable, set item_id to null.
PROMPT;

            $result = $this->callOllama($prompt);
            $json   = json_decode($result, true);

            $results[] = array_merge($line, [
                'matched_item_id' => $json['item_id'] ?? null,
                'ai_confidence'   => $json['confidence'] ?? 'low',
                'ai_reason'       => $json['reason'] ?? '',
            ]);
        }

        return $results;
    }

    private function callOllama(string $prompt): string
    {
        $baseUrl = config('services.ollama.url', env('OLLAMA_BASE_URL', 'http://ollama:11434'));
        $model   = config('services.ollama.model', env('OLLAMA_MODEL', 'llama3.2:3b'));

        $response = Http::timeout(60)->post("{$baseUrl}/api/generate", [
            'model'  => $model,
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama API error: {$response->status()}");
        }

        return $response->json('response', '{}');
    }

    private function persistMappings(Show $show, array $mapped): array
    {
        $created  = 0;
        $skipped  = 0;
        $mappedCount = 0;
        $notes    = [];

        return DB::transaction(function () use ($show, $mapped, &$created, &$skipped, &$mappedCount, &$notes) {
            // Find or create a DeductionRequest for this show
            $dr = $show->deductionRequests()->firstOrCreate(
                ['show_id' => $show->id, 'streamer_id' => null],
                ['status' => 'draft']
            );

            foreach ($mapped as $line) {
                if (empty($line['description'])) {
                    $skipped++;
                    continue;
                }

                // If this came from an existing line, update it
                if (! empty($line['line_id'])) {
                    DeductionRequestLine::where('id', $line['line_id'])->update([
                        'inventory_item_id' => $line['matched_item_id'],
                        'ai_confidence'     => $line['ai_confidence'],
                        'ai_reason'         => $line['ai_reason'],
                    ]);
                } else {
                    $item     = $line['matched_item_id'] ? InventoryItem::find($line['matched_item_id']) : null;
                    $unitCost = $item ? (float) ($item->average_cost ?: $item->unit_cost) : 0;
                    $qty      = (float) $line['quantity'];

                    DeductionRequestLine::create([
                        'deduction_request_id' => $dr->id,
                        'inventory_item_id'    => $line['matched_item_id'],
                        'raw_description'      => $line['description'],
                        'quantity_suggested'   => $qty,
                        'quantity_approved'    => $qty,
                        'unit_cost_snapshot'   => $unitCost,
                        'line_total'           => round($qty * $unitCost, 2),
                        'ai_confidence'        => $line['ai_confidence'],
                        'ai_reason'            => $line['ai_reason'],
                        'ops_overridden'       => false,
                    ]);
                    $created++;
                }

                if ($line['matched_item_id']) {
                    $mappedCount++;
                    $notes[] = "[{$line['ai_confidence']}] {$line['description']}";
                } else {
                    $skipped++;
                    $notes[] = "[unmatched] {$line['description']}";
                }
            }

            $dr->update(['ai_mapping_notes' => implode("\n", $notes)]);
            $dr->update(['status' => 'pending']);

            return [
                'lines_created'  => $created,
                'lines_mapped'   => $mappedCount,
                'lines_skipped'  => $skipped,
                'deduction_request_id' => $dr->id,
            ];
        });
    }

    private function notify(AiTask $task): void
    {
        $userId = $task->triggered_by;
        if (! $userId) {
            return;
        }
        $user = User::find($userId);
        $user?->notify(new AiTaskCompletedNotification($task));
    }
}
