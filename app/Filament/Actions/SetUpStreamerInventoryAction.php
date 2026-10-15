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
    /**
     * "Give each of them" — the optional source and items for setting up many
     * people at once. Each person gets every line.
     *
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    public static function stockEachFields(): array
    {
        $setup = fn () => app(StreamerInventorySetup::class);

        return [
            \Filament\Forms\Components\Toggle::make('stock_each')->label('Give each of them some stock now')->live(),
            Select::make('from_location_id')->label('Take stock from')
                ->options(fn () => $setup()->sources()->pluck('name', 'id')->all())
                ->default(fn () => $setup()->defaultSource(null)?->id)
                ->native(false)->live()
                ->visible(fn ($get) => (bool) $get('stock_each'))
                ->required(fn ($get) => (bool) $get('stock_each'))
                ->afterStateUpdated(fn ($set) => $set('lines', [])),
            Repeater::make('lines')->label('Each person gets')
                ->schema([
                    Select::make('inventory_item_id')->label('Item')
                        ->options(fn ($get) => $setup()->itemOptions((int) $get('../../from_location_id')))
                        ->searchable()->native(false)->required()->columnSpan(['sm' => 2]),
                    TextInput::make('quantity')->label('Each')->numeric()->minValue(0.01)->default(1)->required(),
                ])
                ->columns(['default' => 1, 'sm' => 3])
                ->default([])
                ->addActionLabel('Add an item')
                ->visible(fn ($get) => (bool) $get('stock_each') && filled($get('from_location_id'))),
        ];
    }

    /** Run a many-people setup from a form's data and say how it went. */
    public static function runMany(iterable $streamers, array $data): void
    {
        $setup = app(StreamerInventorySetup::class);
        $from = ! empty($data['stock_each']) && ! empty($data['from_location_id']) ? InventoryLocation::find($data['from_location_id']) : null;
        $summary = $setup->setUpMany($streamers, $from, $from ? array_values($data['lines'] ?? []) : []);

        Notification::make()->title('Inventory set up')->body(StreamerInventorySetup::describe($summary))
            ->{$summary['problems'] ? 'warning' : 'success'}()->send();
    }

    /** Set up everyone in a business (or every streamer), from the Team list. */
    public static function forBusiness(string $name = 'setUpInventoryForBusiness'): Action
    {
        $setup = fn () => app(StreamerInventorySetup::class);

        return Action::make($name)
            ->label('Set up inventory for a business')
            ->icon('heroicon-o-building-storefront')
            ->color('gray')
            ->visible(fn () => auth()->user()?->isAdmin() ?? false)
            ->modalHeading('Set up inventory for a whole business')
            ->modalDescription('Every active streamer in it gets their own location ("Name\'s Inventory"). Anyone who already has one keeps it.')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Set them up')
            // A business's own "Set up inventory" link opens this with it chosen.
            ->fillForm(fn (array $arguments) => [
                'business_id' => (string) ($arguments['business'] ?? (\App\Support\BusinessContext::currentId() ?: 'all')),
                'stock_each' => false,
                'from_location_id' => $setup()->defaultSource(null)?->id,
                'lines' => [],
            ])
            ->schema([
                Select::make('business_id')->label('Business')
                    ->options(fn () => ['all' => 'All streamers'] + \App\Models\Business::ordered()->pluck('name', 'id')->all())
                    ->native(false)->required()->live()
                    ->helperText(function ($get) use ($setup) {
                        $people = $setup()->streamersFor($get('business_id') === 'all' ? null : (int) $get('business_id'));
                        $have = $people->filter(fn ($s) => $setup()->ownLocation($s))->count();

                        return $people->count() . ' active ' . \Illuminate\Support\Str::plural('streamer', $people->count()) . ($have ? " · {$have} already set up" : '');
                    }),
                ...static::stockEachFields(),
            ])
            ->action(function (array $data) use ($setup): void {
                static::runMany($setup()->streamersFor($data['business_id'] === 'all' ? null : (int) $data['business_id']), $data);
            });
    }

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
