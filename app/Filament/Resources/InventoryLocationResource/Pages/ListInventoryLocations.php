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
        return 'Where stock lives, and who sells from it. Streamers use their own locations plus their business\'s storage; a business with no storage of its own shares the Main Storage.';
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
            Action::make('businessStorage')
                ->label('Set up a business\'s storage')
                ->icon('heroicon-o-building-storefront')
                ->color('gray')
                ->visible($admin)
                ->modalHeading('Set up a business\'s storage')
                ->modalDescription('Its streamers will sell from this instead of the shared Main Storage. Receive or transfer stock into it to fill it.')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Create storage')
                ->schema([
                    Select::make('business_id')->label('Business')
                        ->options(fn () => Business::ordered()->pluck('name', 'id')->all())
                        ->native(false)->required()->live()
                        ->afterStateUpdated(fn ($state, $set) => $set('name', $state ? (Business::whereKey($state)->value('name') . ' Storage') : null)),
                    TextInput::make('name')->label('Location name')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $business = Business::findOrFail($data['business_id']);
                    InventoryLocation::create([
                        'name' => $data['name'], 'type' => 'main_storage', 'status' => 'active',
                        'business_id' => $business->id,
                    ]);
                    Notification::make()->title("{$data['name']} is ready")->body("{$business->name}'s streamers now sell from it instead of the shared Main Storage.")->success()->send();
                }),

            CreateAction::make()->label('New location'),
        ];
    }
}
