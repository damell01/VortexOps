<?php

namespace App\Jobs;

use App\Models\AiTask;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\User;
use App\Notifications\AiTaskCompletedNotification;
use App\Services\AI\Mapping\MappingEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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

    public function handle(MappingEngine $engine): void
    {
        $task = AiTask::findOrFail($this->aiTaskId);
        $show = Show::with(['streamers', 'deductionRequests.lines', 'orders'])->findOrFail($this->showId);

        $task->markProcessing();
        $show->update(['status' => 'mapping']);

        try {
            $lines   = $this->extractRawLines($show);
            $mapped  = $this->mapLines($lines, $engine);
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

    public function failed(\Throwable $e): void
    {
        Log::error('RunShowAiMappingJob timed out or failed fatally', [
            'show_id'    => $this->showId,
            'ai_task_id' => $this->aiTaskId,
            'error'      => $e->getMessage(),
        ]);

        optional(AiTask::find($this->aiTaskId))->markFailed($e->getMessage());
        optional(Show::find($this->showId))->update(['status' => 'pending_review']);
    }

    private function extractRawLines(Show $show): array
    {
        $lines = [];

        // Primary: WhatnotShowOrder records
        if ($show->orders->isNotEmpty()) {
            foreach ($show->orders->filter(fn ($o) => ! empty($o->item_name))->groupBy('item_name') as $itemName => $group) {
                $lines[] = [
                    'description' => $itemName,
                    'quantity'    => (float) $group->sum('quantity'),
                    'source'      => 'whatnot_order',
                ];
            }
        }

        // Secondary: raw_import_payload if orders table is empty
        if (empty($lines)) {
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
        }

        // Tertiary: existing DeductionRequest lines without a match
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

    private function mapLines(array $lines, MappingEngine $engine): array
    {
        $results = [];

        foreach ($lines as $line) {
            if (empty($line['description'])) {
                continue;
            }

            try {
                $result = $engine->match($line['description']);

                $results[] = array_merge($line, [
                    'matched_item_id'  => $result->product?->id,
                    'ai_confidence'    => $result->confidenceLabel(),
                    'ai_reason'        => implode('; ', array_slice($result->reasons, 0, 2)),
                    'confidence_score' => $result->confidence,
                    'stage'            => $result->stage,
                ]);
            } catch (\Throwable $e) {
                Log::warning("MappingEngine failed for '{$line['description']}': {$e->getMessage()}");
                $results[] = array_merge($line, [
                    'matched_item_id' => null,
                    'ai_confidence'   => 'low',
                    'ai_reason'       => 'Matching error: ' . $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    private function persistMappings(Show $show, array $mapped): array
    {
        $created     = 0;
        $skipped     = 0;
        $mappedCount = 0;
        $notes       = [];

        $matchedItemIds = collect($mapped)->pluck('matched_item_id')->filter()->unique()->values()->toArray();
        $itemsById      = InventoryItem::whereIn('id', $matchedItemIds)->get()->keyBy('id');

        return DB::transaction(function () use ($show, $mapped, $itemsById, &$created, &$skipped, &$mappedCount, &$notes) {
            $defaultLocation = $show->defaultInventoryLocation();
            $primaryStreamer  = $show->streamers->first();

            $dr = $show->deductionRequests()->firstOrCreate(
                ['show_id' => $show->id],
                ['status' => 'draft', 'streamer_id' => $primaryStreamer?->id]
            );

            foreach ($mapped as $line) {
                if (empty($line['description'])) {
                    $skipped++;
                    continue;
                }

                if (! empty($line['line_id'])) {
                    DeductionRequestLine::where('id', $line['line_id'])->update([
                        'inventory_item_id' => $line['matched_item_id'],
                        'ai_confidence'     => $line['ai_confidence'],
                        'ai_reason'         => $line['ai_reason'],
                    ]);
                } else {
                    $item     = $line['matched_item_id'] ? ($itemsById->get($line['matched_item_id']) ?? InventoryItem::find($line['matched_item_id'])) : null;
                    $unitCost = $item ? (float) ($item->average_cost ?: $item->unit_cost) : 0;
                    $qty      = (float) $line['quantity'];

                    DeductionRequestLine::create([
                        'deduction_request_id'  => $dr->id,
                        'inventory_item_id'     => $line['matched_item_id'],
                        'inventory_location_id' => $defaultLocation?->id,
                        'raw_description'       => $line['description'],
                        'quantity_suggested'    => $qty,
                        'quantity_approved'     => $qty,
                        'unit_cost_snapshot'    => $unitCost,
                        'line_total'            => round($qty * $unitCost, 2),
                        'ai_confidence'         => $line['ai_confidence'],
                        'ai_reason'             => $line['ai_reason'],
                        'ops_overridden'        => false,
                    ]);
                    $created++;
                }

                if ($line['matched_item_id']) {
                    $mappedCount++;
                    $stage = isset($line['stage']) ? "[{$line['stage']}]" : '';
                    $notes[] = "[{$line['ai_confidence']}]{$stage} {$line['description']}";
                } else {
                    $skipped++;
                    $notes[] = "[unmatched] {$line['description']}";
                }
            }

            $dr->update(['ai_mapping_notes' => implode("\n", $notes), 'status' => 'pending']);

            return [
                'lines_created'        => $created,
                'lines_mapped'         => $mappedCount,
                'lines_skipped'        => $skipped,
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
        User::find($userId)?->notify(new AiTaskCompletedNotification($task));
    }
}
