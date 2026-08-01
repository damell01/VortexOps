<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use App\Models\StreamerLogEntry;
use App\Services\ShippingSurchargeService;
use Filament\Resources\Pages\EditRecord;

class EditStreamerLogEntry extends EditRecord
{
    protected static string $resource = StreamerLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        /** @var StreamerLogEntry $record */
        $record = $this->record;

        if (StreamerLogResource::isLockedForCurrentUser($record)) {
            return '🔒 Approved — this entry is now view-only. Ask an admin to send it back if you need to make changes.';
        }

        if ($record->status === 'pending') {
            $total = $record->show?->orders()->count() ?? 0;
            $mapped = $record->show?->orders()->whereNotNull('inventory_item_id')->count() ?? 0;

            $mappingProgress = $total > 0
                ? "{$mapped}/{$total} items mapped"
                : 'No items to map';

            return "Step 1: Map items → Step 2: Fill costs → Step 3: Review. {$mappingProgress}";
        }

        if ($record->status === 'streamer_reviewed') {
            return 'Waiting for admin review. Your data has been submitted.';
        }

        if ($record->status === 'admin_approved' && $record->needsFulfillmentReview()) {
            return 'Pending fulfillment team review of PWE/label counts.';
        }

        return null;
    }

    protected function afterSave(): void
    {
        /** @var StreamerLogEntry $record */
        $record = $this->record;

        // Auto-fill gross_revenue from the show if it wasn't set.
        if (! $record->gross_revenue && $record->show) {
            $record->gross_revenue = $record->show->gross_revenue;
        }

        // Recalculate profit share once we have cost data.
        if ($record->gross_revenue && $record->product_cost !== null) {
            $record->profit_share_amount = $record->profitShareAmount();
        }
        $record->save();

        // Auto-create a shipping surcharge when packages over $500 are logged.
        if (($record->number_of_packages_over_500 ?? 0) > 0 && $record->show && $record->streamer) {
            app(ShippingSurchargeService::class)->createForShow(
                $record->show,
                $record->streamer,
                $record->number_of_packages_over_500,
                "Auto from streamer log #{$record->id}",
            );
        }
    }
}
