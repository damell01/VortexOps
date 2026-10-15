<?php

namespace App\Filament\Actions;

use App\Models\InventoryLocation;
use App\Models\Streamer;
use App\Services\StreamerInventorySetup;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * One button for a streamer's own stock: makes their location if they don't
 * have one, and (optionally) moves stock into it in the same step.
 *
 * Labelled "Set up inventory" until they have a location, "Add inventory"
 * after. Used on the streamer's page and the Streamers list.
 */
class SetUpStreamerInventoryAction
{
    public static function make(string $name = 'setUpInventory'): Action
    {
        $setup = fn () => app(StreamerInventorySetup::class);
        $has = fn (?Streamer $record) => $record && $setup()->ownLocation($record) !== null;

        return Action::make($name)
            ->label(fn (?Streamer $record) => $has($record) ? 'Add inventory' : 'Set up inventory')
            ->icon(fn (?Streamer $record) => $has($record) ? 'heroicon-o-arrow-right-circle' : 'heroicon-o-archive-box-arrow-down')
            ->visible(fn (?Streamer $record) => (auth()->user()?->isAdmin() ?? false) && $record && ! $record->isFulfillment())
            ->modalHeading(fn (?Streamer $record) => $has($record) ? "Add inventory for {$record->name}" : "Set up {$record?->name}'s inventory")
            ->modalDescription(fn (?Streamer $record) => $has($record)
                ? 'Move stock into ' . $setup()->ownLocation($record)->name . '.'
                : 'Creates their own location so they can hold and sell their own stock. Add items now, or leave the list empty and stock it later.')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel(fn (?Streamer $record) => $has($record) ? 'Move stock' : 'Set up')
            ->schema(fn (?Streamer $record) => [
                TextInput::make('name')->label('Location name')
                    ->default($record ? "{$record->name}'s Inventory" : null)
                    ->required()->maxLength(255)
                    ->visible(! $has($record)),
                Select::make('from_location_id')->label('Take stock from')
                    ->options(fn () => $setup()->sources()->pluck('name', 'id')->all())
                    // Their business's storage if it has its own, else the receiving warehouse or Main Storage.
                    ->default(fn () => $setup()->defaultSource($record)?->id)
                    ->native(false)->live()
                    ->afterStateUpdated(fn ($set) => $set('lines', [])),
                Repeater::make('lines')->label('Items')
                    ->schema([
                        Select::make('inventory_item_id')->label('Item')
                            ->options(fn ($get) => $setup()->itemOptions((int) $get('../../from_location_id')))
                            ->searchable()->native(false)->required()->columnSpan(['sm' => 2]),
                        TextInput::make('quantity')->numeric()->minValue(0.01)->default(1)->required(),
                    ])
                    ->columns(['default' => 1, 'sm' => 3])
                    ->default([])
                    ->addActionLabel('Add an item')
                    ->visible(fn ($get) => filled($get('from_location_id'))),
            ])
            ->action(function (array $data, Streamer $record) use ($setup): void {
                [$location, $created] = $setup()->ensure($record, $data['name'] ?? null);
                $lines = array_values($data['lines'] ?? []);
                $result = ['moved' => 0, 'problems' => []];
                if ($lines && ! empty($data['from_location_id'])) {
                    $result = $setup()->stock($record, $location, InventoryLocation::findOrFail($data['from_location_id']), $lines);
                }

                $title = $created ? "{$location->name} is ready" : "Inventory added for {$record->name}";
                $body = $result['moved'] ? "{$result['moved']} " . \Illuminate\Support\Str::plural('item', $result['moved']) . " moved in." : ($created ? "{$record->name} can sell from it now." : 'Nothing was moved.');
                if ($result['problems']) {
                    $body .= ' Not moved: ' . implode('; ', $result['problems']);
                }
                Notification::make()->title($title)->body($body)->{$result['problems'] ? 'warning' : 'success'}()->send();
            });
    }
}
