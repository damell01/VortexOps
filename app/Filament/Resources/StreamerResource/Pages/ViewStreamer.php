<?php

namespace App\Filament\Resources\StreamerResource\Pages;

use App\Filament\Resources\StreamerResource;
use App\Filament\Widgets\StreamerEarningsChartWidget;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Streamer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStreamer extends ViewRecord
{
    protected static string $resource = StreamerResource::class;
    protected string $view = 'filament.resources.streamer-resource.pages.view-streamer-workspace';

    public function getTitle(): string
    {
        return $this->record->name ?: 'Team Member';
    }

    public function getSubheading(): ?string
    {
        $status = Streamer::statusLabels()[$this->record->status] ?? ucfirst((string) $this->record->status);
        $channel = $this->record->channel?->name;
        $type = Streamer::payoutTypeLabels()[$this->record->payout_type] ?? ucfirst(str_replace('_', ' ', (string) $this->record->payout_type));
        return implode(' · ', array_filter([$status, $type, $channel]));
    }

    protected function getHeaderWidgets(): array { return []; }

    public function getWidgetData(): array { return ['record' => $this->record]; }

    protected function getHeaderActions(): array
    {
        // One button for their own stock: sets up their location if they
        // don't have one, and moves items in (SetUpStreamerInventoryAction).
        $allocate = \App\Filament\Actions\SetUpStreamerInventoryAction::make()->color('primary');

        return [
            $allocate,
            ActionGroup::make([
                EditAction::make()->label('Edit Team Member')->icon('heroicon-o-pencil-square'),
            ])->label('More')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray'),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [StreamerEarningsChartWidget::class, \App\Filament\Widgets\StreamerPokedexWidget::class];
    }
}
