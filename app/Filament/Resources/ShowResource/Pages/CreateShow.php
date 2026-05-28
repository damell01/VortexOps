<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Jobs\NotifyShowReady;
use App\Jobs\ParseShowTitle;
use App\Services\ShowSalesWorksheetService;
use Filament\Resources\Pages\CreateRecord;

class CreateShow extends CreateRecord
{
    protected static string $resource = ShowResource::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $manualSoldItems = [];

    protected ?int $manualSalesStreamerId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->manualSoldItems = $data['manual_sold_items'] ?? [];
        $this->manualSalesStreamerId = isset($data['manual_sales_streamer_id']) ? (int) $data['manual_sales_streamer_id'] : null;

        unset($data['manual_sold_items'], $data['manual_sales_streamer_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $show = $this->record;
        $show->update([
            'status'     => 'pending_review',
            'created_by' => auth()->id(),
        ]);

        if ($show->title) {
            ParseShowTitle::dispatch($show->id);
        }

        $streamerId = $this->manualSalesStreamerId ?: $show->streamers()->value('streamers.id');

        if ($streamerId && collect($this->manualSoldItems)->filter(fn (array $line): bool => filled($line['inventory_item_id'] ?? null))->isNotEmpty()) {
            app(ShowSalesWorksheetService::class)->storeManualSoldItems(
                show: $show,
                streamerId: (int) $streamerId,
                lines: $this->manualSoldItems,
            );
        }

        NotifyShowReady::dispatch($show->id);
    }
}
