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
