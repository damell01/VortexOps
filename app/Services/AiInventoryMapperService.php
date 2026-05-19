<?php

namespace App\Services;

use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Show;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiInventoryMapperService
{
    public function __construct(private OllamaService $ollama) {}

    public function map(Show $show): void
    {
        try {
            $streamer = $show->primaryStreamer();

            if (! $streamer) {
                Log::info('AiInventoryMapperService: no primary streamer on show', ['show_id' => $show->id]);
                return;
            }

            $show->update(['status' => 'mapping']);

            // Load streamer's inventory locations + available stock
            $locations = InventoryLocation::where('streamer_id', $streamer->id)
                ->orWhereNull('streamer_id')
                ->where('status', 'active')
                ->with('stock.item')
                ->get();

            $stockCatalogue = [];
            foreach ($locations as $location) {
                foreach ($location->stock as $stock) {
                    if ($stock->quantity > 0 && $stock->item) {
                        $stockCatalogue[] = [
                            'inventory_item_id'   => $stock->inventory_item_id,
                            'inventory_location_id' => $location->id,
                            'item_name'           => $stock->item->name,
                            'sku'                 => $stock->item->sku,
                            'category'            => $stock->item->category,
                            'available_qty'       => (float) $stock->quantity,
                            'unit_cost'           => (float) ($stock->item->unit_cost ?? 0),
                            'location_name'       => $location->name,
                        ];
                    }
                }
            }

            $prompt = "A Whatnot sports card show titled \"{$show->title}\" had {$show->units_sold} units sold "
                . "with gross revenue of \${$show->gross_revenue}. "
                . "Map the show's sold units to the available inventory below. "
                . "Suggest which inventory items were likely sold and how many. "
                . "Respond ONLY with valid JSON: {\"mapping_notes\": \"...\", \"lines\": [{\"inventory_item_id\": 1, \"inventory_location_id\": 2, \"quantity_suggested\": 3, \"ai_confidence\": \"high|medium|low\", \"ai_reason\": \"...\", \"raw_description\": \"...\"}]}. "
                . "Available inventory: " . json_encode($stockCatalogue);

            $result = $this->ollama->json($prompt);

            DB::transaction(function () use ($show, $streamer, $result, $stockCatalogue) {
                $request = DeductionRequest::create([
                    'show_id'          => $show->id,
                    'streamer_id'      => $streamer->id,
                    'status'           => 'pending',
                    'ai_mapping_notes' => $result['mapping_notes'] ?? null,
                ]);

                $lines = $result['lines'] ?? [];

                foreach ($lines as $line) {
                    $itemId     = $line['inventory_item_id'] ?? null;
                    $locationId = $line['inventory_location_id'] ?? null;

                    if (! $itemId || ! $locationId) {
                        continue;
                    }

                    $stockEntry = collect($stockCatalogue)->firstWhere(function ($s) use ($itemId, $locationId) {
                        return $s['inventory_item_id'] === $itemId && $s['inventory_location_id'] === $locationId;
                    });

                    $unitCost  = $stockEntry['unit_cost'] ?? 0;
                    $qtySugg   = (float) ($line['quantity_suggested'] ?? 0);
                    $lineTotal = round($qtySugg * $unitCost, 2);

                    DeductionRequestLine::create([
                        'deduction_request_id'  => $request->id,
                        'inventory_item_id'     => $itemId,
                        'inventory_location_id' => $locationId,
                        'quantity_suggested'    => $qtySugg,
                        'quantity_approved'     => $qtySugg,
                        'unit_cost_snapshot'    => $unitCost,
                        'line_total'            => $lineTotal,
                        'raw_description'       => $line['raw_description'] ?? null,
                        'ai_confidence'         => $line['ai_confidence'] ?? 'manual',
                        'ai_reason'             => $line['ai_reason'] ?? null,
                    ]);
                }

                $show->update(['status' => 'pending_approval']);
            });
        } catch (\Exception $e) {
            Log::error('AiInventoryMapperService failed', ['show_id' => $show->id, 'error' => $e->getMessage()]);
            $show->update(['status' => 'pending_review']);
        }
    }
}
