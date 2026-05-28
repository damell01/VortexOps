<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Services\ShowSalesWorksheetService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShow extends EditRecord
{
    protected static string $resource = ShowResource::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $manualSoldItems = [];

    protected ?int $manualSalesStreamerId = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->manualSoldItems = $data['manual_sold_items'] ?? [];
        $this->manualSalesStreamerId = isset($data['manual_sales_streamer_id']) ? (int) $data['manual_sales_streamer_id'] : null;

        unset($data['manual_sold_items'], $data['manual_sales_streamer_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        $streamerId = $this->manualSalesStreamerId ?: $this->record->streamers()->value('streamers.id');

        if (! $streamerId) {
            return;
        }

        $lines = collect($this->manualSoldItems)
            ->filter(fn (array $line): bool => filled($line['inventory_item_id'] ?? null))
            ->values()
            ->all();

        if ($lines === []) {
            return;
        }

        app(ShowSalesWorksheetService::class)->storeManualSoldItems(
            show: $this->record,
            streamerId: (int) $streamerId,
            lines: $lines,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
