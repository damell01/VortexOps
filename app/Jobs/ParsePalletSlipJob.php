<?php

namespace App\Jobs;

use App\Filament\Resources\PalletResource;
use App\Models\AiTask;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Services\AI\Documents\PalletSlipParser;
use App\Services\AI\Mapping\MappingEngine;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParsePalletSlipJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public readonly int $palletId,
        public readonly int $aiTaskId,
        public readonly string $storedPath,
    ) {}

    public function handle(PalletSlipParser $parser, MappingEngine $mapping): void
    {
        $task = AiTask::findOrFail($this->aiTaskId);
        $pallet = Pallet::with('vendor')->findOrFail($this->palletId);
        $task->markProcessing();

        try {
            $rawLines = $parser->parse($this->storedPath);
            $reviewLines = [];

            foreach ($rawLines as $line) {
                $description = trim((string) ($line['description'] ?? ''));
                if ($description === '') continue;

                $barcode = filled($line['barcode'] ?? null) ? trim((string) $line['barcode']) : null;
                $sku = filled($line['sku'] ?? null) ? trim((string) $line['sku']) : null;
                $matchedId = null;
                $matchedName = null;
                $confidence = '';
                $confidenceScore = 0.0;
                $stage = '';
                $reasons = [];
                $alternatives = [];

                try {
                    $result = $mapping->match(
                        description: $description,
                        upc: $barcode,
                        vendorId: $pallet->vendor_id,
                        skipLlm: false,
                    );

                    if ($result->matched()) {
                        $matchedId = $result->product->id;
                        $matchedName = $result->product->name;
                        $confidence = $result->confidenceLabel();
                        $confidenceScore = $result->confidence;
                        $stage = $result->stage;
                        $reasons = array_values(array_filter($result->reasons));
                    }

                    $alternatives = collect($result->candidates ?? [])
                        ->map(function ($candidate) {
                            $product = $candidate['product'] ?? null;
                            if (! $product) return null;
                            return [
                                'id' => $product->id,
                                'name' => $product->name,
                                'sku' => $product->sku,
                                'score' => isset($candidate['confidence']) ? (float) $candidate['confidence'] : null,
                            ];
                        })
                        ->filter()
                        ->unique('id')
                        ->take(5)
                        ->values()
                        ->all();
                } catch (\Throwable $matchError) {
                    Log::warning('Manifest line matching failed', [
                        'task' => $task->id,
                        'description' => $description,
                        'error' => $matchError->getMessage(),
                    ]);
                }

                $reviewLines[] = [
                    'description' => $description,
                    'case_count' => (string) max(1, (int) ($line['case_count'] ?? 1)),
                    'quantity_per_case' => (string) max(1, (int) ($line['quantity_per_case'] ?? 1)),
                    'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== null
                        ? number_format((float) $line['unit_cost'], 2, '.', '')
                        : '',
                    'sku' => $sku ?? '',
                    'barcode' => $barcode ?? '',
                    'matched_item_id' => $matchedId,
                    'matched_item_name' => $matchedName,
                    'match_confidence' => $confidence,
                    'match_confidence_score' => $confidenceScore,
                    'match_stage' => $stage,
                    'match_reasons' => $reasons,
                    'alternatives' => $alternatives,
                    'create_new_item' => $matchedId === null,
                    'pallet_line_id' => null,
                ];
            }

            // The extraction itself is the useful automation. Stage every valid
            // extracted row on the pallet immediately so the receiver never has
            // to re-type a manifest just because inventory matching was uncertain.
            // Existing-item suggestions are preselected when confidence is high
            // enough for MappingEngine to return a product; unmatched lines stay
            // as ordinary unmapped manifest lines for human review.
            DB::transaction(function () use ($pallet, $task, &$reviewLines) {
                $nextLine = ($pallet->lines()->max('line_number') ?? 0) + 1;

                foreach ($reviewLines as &$row) {
                    $unitCost = (float) str_replace(['$', ','], '', (string) ($row['unit_cost'] ?? '0'));
                    $matchedId = $row['matched_item_id'] ?? null;

                    $line = PalletLine::create([
                        'pallet_id' => $pallet->id,
                        'line_number' => $nextLine++,
                        'description' => $row['description'],
                        'vendor_description' => $row['description'],
                        'case_count' => max(1, (int) ($row['case_count'] ?? 1)),
                        'quantity_per_case' => max(1, (float) ($row['quantity_per_case'] ?? 1)),
                        'unit_cost' => max(0, $unitCost),
                        'inventory_item_id' => $matchedId,
                        'match_confidence' => $matchedId ? (float) ($row['match_confidence_score'] ?? 0) : null,
                        'match_stage' => $matchedId ? (($row['match_stage'] ?? '') ?: 'ai_suggested') : 'ai_unmatched',
                        'match_reasons' => $row['match_reasons'] ?? [],
                        'matched_at' => $matchedId ? now() : null,
                        'matched_by' => $matchedId ? $task->triggered_by : null,
                    ]);

                    $row['pallet_line_id'] = $line->id;
                }
                unset($row);
            });

            $matched = collect($reviewLines)->whereNotNull('matched_item_id')->count();
            $needsReview = collect($reviewLines)->filter(fn ($line) =>
                empty($line['matched_item_id']) || (float) ($line['match_confidence_score'] ?? 0) < .95
            )->count();

            $task->markCompleted([
                'lines' => $reviewLines,
                'summary' => [
                    'total' => count($reviewLines),
                    'matched' => $matched,
                    'new_items' => count($reviewLines) - $matched,
                    'needs_review' => $needsReview,
                    'manifest_lines_created' => count($reviewLines),
                ],
            ]);

            $this->notifyFinished($task, $pallet, count($reviewLines), $matched, $needsReview);
        } catch (\Throwable $e) {
            Log::error('ParsePalletSlipJob failed', ['error' => $e->getMessage(), 'task' => $this->aiTaskId]);
            $task->markFailed($e->getMessage());
            $this->notifyFailed($task, $pallet, $e->getMessage());
        } finally {
            @unlink($this->storedPath);
        }
    }

    private function notifyFinished(AiTask $task, Pallet $pallet, int $total, int $matched, int $needsReview): void
    {
        $user = User::find($task->triggered_by);
        if (! $user) return;

        Notification::make()
            ->title('AI manifest ready for review')
            ->body("{$pallet->displayName()}: {$total} manifest lines added · {$matched} existing items preselected · {$needsReview} need review.")
            ->success()
            ->icon('heroicon-o-sparkles')
            ->actions([
                NotificationAction::make('review')
                    ->label('Open AI Review')
                    ->button()
                    ->url(PalletResource::getUrl('import-manifest', ['record' => $pallet])),
                NotificationAction::make('pallet')
                    ->label('Open Pallet')
                    ->url(PalletResource::getUrl('view', ['record' => $pallet])),
            ])
            ->sendToDatabase($user);
    }

    private function notifyFailed(AiTask $task, Pallet $pallet, string $message): void
    {
        $user = User::find($task->triggered_by);
        if (! $user) return;

        Notification::make()
            ->title('AI manifest analysis failed')
            ->body($pallet->displayName() . ': ' . str($message)->limit(180))
            ->danger()
            ->icon('heroicon-o-exclamation-triangle')
            ->actions([
                NotificationAction::make('retry')
                    ->label('Open AI Manifest')
                    ->button()
                    ->url(PalletResource::getUrl('import-manifest', ['record' => $pallet])),
                NotificationAction::make('pallet')
                    ->label('Open Pallet')
                    ->url(PalletResource::getUrl('view', ['record' => $pallet])),
            ])
            ->sendToDatabase($user);
    }

    public function failed(\Throwable $e): void
    {
        @unlink($this->storedPath);
        Log::error('ParsePalletSlipJob timed out or failed fatally', [
            'ai_task_id' => $this->aiTaskId,
            'error' => $e->getMessage(),
        ]);

        $task = AiTask::find($this->aiTaskId);
        if ($task) {
            $task->markFailed($e->getMessage());
            if ($pallet = Pallet::find($this->palletId)) {
                $this->notifyFailed($task, $pallet, $e->getMessage());
            }
        }
    }
}
