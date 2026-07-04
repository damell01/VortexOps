<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Product;
use App\Models\ProductIdentity;
use App\Models\ReceivingSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the full receiving workflow:
 *
 *   1. Create a ReceivingSession
 *   2. Import lines (from manual entry, parsed PDF, or Excel)
 *   3. Run matching pipeline on each line
 *   4. Present review UI for lines that need human attention
 *   5. Confirm matches → create InventoryLots → credit stock
 */
class ReceivingSessionService
{
    public function __construct(
        private readonly ProductMatchingService $matcher,
        private readonly ReceivingService $receiving,
    ) {}

    // ── Session lifecycle ──────────────────────────────────────────────────────

    public function createSession(array $attributes = []): ReceivingSession
    {
        return ReceivingSession::create(array_merge([
            'received_by' => Auth::id(),
            'status'      => ReceivingSession::STATUS_PENDING,
        ], $attributes));
    }

    /**
     * Import structured line items into a session's pallet.
     * Each $lines entry: {description, qty, unit_cost, upc?, vendor_sku?}
     *
     * Runs the matching pipeline on each line and updates session summary counts.
     */
    public function importLines(ReceivingSession $session, Pallet $pallet, array $lines): void
    {
        $session->update(['status' => ReceivingSession::STATUS_PARSING]);

        DB::transaction(function () use ($session, $pallet, $lines) {
            $vendorId = $pallet->vendor_id ?? $session->vendor_id;

            foreach ($lines as $i => $line) {
                $description = trim($line['description'] ?? '');
                $qty         = max(0, (float) ($line['qty'] ?? 0));
                $unitCost    = max(0, (float) ($line['unit_cost'] ?? 0));
                $upc         = $line['upc'] ?? null;

                if ($description === '' && ! $upc) {
                    continue;
                }

                // Run matching pipeline
                $match = $this->matcher->match($description, $upc, $vendorId);

                $palletLine = PalletLine::create([
                    'pallet_id'            => $pallet->id,
                    'receiving_session_id' => $session->id,
                    'line_number'          => $i + 1,
                    'description'          => $match['product']?->name ?? $description,
                    'vendor_description'   => $description,
                    'inventory_item_id'    => $match['product']?->id,
                    'product_identity_id'  => $match['identity']?->id,
                    'match_confidence'     => $match['confidence'],
                    'match_stage'          => $match['stage'],
                    'match_reasons'        => $match['reasons'] ?? [],
                    'matched_at'           => $match['product'] ? now() : null,
                    'matched_by'           => null,
                    'case_count'           => max(1, (int) ceil($qty)),
                    'quantity_per_case'    => 1,
                    'unit_cost'            => $unitCost,
                    'inventory_location_id' => null,
                ]);
            }
        });

        $session->recalculateSummary();
        $session->update(['status' => ReceivingSession::STATUS_REVIEWING]);
    }

    // ── Line confirmation ──────────────────────────────────────────────────────

    /**
     * Confirm a product match for a pallet line (human review step).
     * Saves the alias for future instant lookups, then creates the lot.
     */
    public function confirmLineMatch(PalletLine $line, Product $product, int $confirmedByUserId): void
    {
        DB::transaction(function () use ($line, $product, $confirmedByUserId) {
            $vendorId = $line->pallet?->vendor_id;

            // Save confirmed match as an alias identity
            $identity = null;
            if ($line->vendor_description) {
                $identity = $this->matcher->confirmMatch(
                    vendorDescription: $line->vendor_description,
                    product: $product,
                    confidence: max((float) $line->match_confidence, 0.95),
                    stage: $line->match_stage ?? 'human',
                    confirmedByUserId: $confirmedByUserId,
                    vendorId: $vendorId,
                );
            }

            $line->update([
                'inventory_item_id'   => $product->id,
                'product_identity_id' => $identity?->id,
                'match_confidence'    => 1.0,
                'match_stage'         => 'human',
                'match_reasons'       => array_merge($line->match_reasons ?? [], ['Confirmed by reviewer']),
                'matched_at'          => now(),
                'matched_by'          => $confirmedByUserId,
            ]);
        });

        // Recalculate session summary after each confirmation
        if ($line->receivingSession) {
            $line->receivingSession->recalculateSummary();
        }
    }

    /**
     * Complete the session: receive all fully-mapped lines, create lots.
     */
    public function completeSession(ReceivingSession $session): array
    {
        $session->update(['status' => ReceivingSession::STATUS_REVIEWING]);

        $totalCases = 0;
        $lotsCreated = 0;
        $skipped = 0;

        DB::transaction(function () use ($session, &$totalCases, &$lotsCreated, &$skipped) {
            foreach ($session->palletLines()->with(['pallet', 'product'])->get() as $line) {
                if (! $line->inventory_item_id || ! $line->inventory_location_id) {
                    $skipped++;
                    continue;
                }

                $cases = $this->receiving->receiveAllCasesForLine($line);
                $totalCases += $cases;

                // Create inventory lot for this line if not already created
                if (! $line->lot) {
                    InventoryLot::create([
                        'product_id'          => $line->inventory_item_id,
                        'pallet_line_id'      => $line->id,
                        'receiving_session_id' => $session->id,
                        'received_by'         => Auth::id(),
                        'quantity'            => $line->totalQuantityExpected(),
                        'unit_cost'           => $line->unit_cost,
                        'remaining_quantity'  => $line->totalQuantityExpected(),
                        'supplier_invoice'    => $session->invoice_number,
                        'purchase_order'      => $session->purchase_order,
                        'vendor_sku'          => $line->vendor_description,
                        'source'              => InventoryLot::SOURCE_RECEIVED,
                        'status'              => InventoryLot::STATUS_ACTIVE,
                        'received_at'         => now(),
                    ]);
                    $lotsCreated++;
                }
            }

            // Mark all pallets in session as received
            $session->pallets()->update(['status' => 'received']);
            $session->update(['status' => ReceivingSession::STATUS_COMPLETED]);
        });

        return [
            'cases_received' => $totalCases,
            'lots_created'   => $lotsCreated,
            'lines_skipped'  => $skipped,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Return all lines for a session with their match status bucket.
     *
     * @return array{auto: PalletLine[], review: PalletLine[], new: PalletLine[]}
     */
    public function groupedLines(ReceivingSession $session): array
    {
        $lines = $session->palletLines()->with(['product', 'productIdentity'])->get();

        $auto   = [];
        $review = [];
        $new    = [];

        foreach ($lines as $line) {
            $confidence = (float) $line->match_confidence;
            if ($line->inventory_item_id && $confidence >= 0.95) {
                $auto[] = $line;
            } elseif ($line->inventory_item_id && $confidence >= 0.80) {
                $review[] = $line;
            } else {
                $new[] = $line;
            }
        }

        return compact('auto', 'review', 'new');
    }
}
