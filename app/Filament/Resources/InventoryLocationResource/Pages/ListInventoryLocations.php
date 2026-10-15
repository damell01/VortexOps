<?php

namespace App\Filament\Resources\InventoryLocationResource\Pages;

use App\Filament\Resources\InventoryLocationResource;
use App\Models\Business;
use App\Models\InventoryLocation;
use App\Models\Streamer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInventoryLocations extends ListRecords
{
    use \App\Filament\Concerns\DownloadsTableCsv;

    protected static string $resource = InventoryLocationResource::class;

    public function getSubheading(): ?string
    {
        return 'Where stock lives, and who sells from it. Streamers use their own locations plus their business\'s storage, which can be shared by several businesses; a business with no storage of its own uses the general Main Storage.';
    }

    protected function getHeaderActions(): array
    {
        $admin = auth()->user()?->isAdmin() ?? false;

        return [
            // A streamer's own stock: one pick, the rest is filled in.
            Action::make('streamerLocation')
                ->label('Give a streamer their own location')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->visible($admin)
                ->modalHeading('Give a streamer their own location')
                ->modalDescription('They\'ll sell from it as well as their business\'s storage. Move stock in with a transfer once it\'s made.')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Create location')
                ->schema([
                    Select::make('streamer_id')->label('Streamer')
                        ->options(fn () => InventoryLocationResource::streamerOptions())
                        ->searchable()->native(false)->required()->live()
                        ->afterStateUpdated(fn ($state, $set) => $set('name', $state ? (Streamer::whereKey($state)->value('name') . '\'s Inventory') : null)),
                    TextInput::make('name')->label('Location name')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $streamer = Streamer::findOrFail($data['streamer_id']);
                    InventoryLocation::create([
                        'name' => $data['name'], 'type' => 'streamer_inventory', 'status' => 'active',
                        'streamer_id' => $streamer->id, 'business_id' => $streamer->business_id,
                        'whatnot_channel_id' => $streamer->whatnot_channel_id,
                    ]);
                    Notification::make()->title("{$data['name']} is ready")->body("{$streamer->name} can sell from it now.")->success()->send();
                }),

            // A business's own allocation, so its streamers stop sharing Main Storage.
            // A business's own allocation, or one shared by several, so their
            // streamers stop sharing the general Main Storage.
            Action::make('businessStorage')
                ->label('Set up business storage')
                ->icon('heroicon-o-building-storefront')
                ->color('gray')
                ->visible($admin)
                ->modalHeading('Set up business storage')
                ->modalDescription('Their streamers will sell from this instead of the shared Main Storage. Pick one business, or several to share it. Receive or transfer stock into it to fill it.')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Create storage')
                ->schema([
                    \Filament\Forms\Components\CheckboxList::make('business_ids')->label('Shared by')
                        ->options(fn () => Business::ordered()->pluck('name', 'id')->all())
                        ->required()->live()
                        ->afterStateUpdated(fn ($state, $set) => $set('name', match (count((array) $state)) {
                            0 => null,
                            1 => Business::whereKey(reset($state))->value('name') . ' Storage',
                            default => 'Shared Storage',
                        })),
                    TextInput::make('name')->label('Location name')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $ids = array_map('intval', (array) $data['business_ids']);
                    $location = InventoryLocation::create(['name' => $data['name'], 'type' => 'main_storage', 'status' => 'active']);
                    $location->businesses()->sync($ids);
                    $names = $location->sharedBusinessNames();
                    Notification::make()->title("{$data['name']} is ready")->body("{$names} streamers now sell from it instead of the shared Main Storage.")->success()->send();
                }),

            CreateAction::make()->label('New location'),
        ];
    }
}
