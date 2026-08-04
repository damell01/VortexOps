<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ShipmentResource\Pages;
use App\Models\Shipment;
use App\Models\Show;
use App\Support\AdminModules;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;

class ShipmentResource extends Resource
{
    use HasModuleAccess;

    protected static ?string $model = Shipment::class;
    protected static string $moduleSlug = 'streams';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationLabel(): string
    {
        return 'Shipments';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Read-only view of shipment data
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('show.title')
                    ->label('Show')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Shipment $record) => $record->show ? route('filament.admin.resources.shows.edit', $record->show) : null),

                TextColumn::make('show.show_date')
                    ->label('Show Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('buyer_username')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->badge()
                    ->color('info')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('carrier')
                    ->label('Carrier')
                    ->color('primary'),

                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => view('components.status-badge', [
                        'status' => $state,
                        'label' => str_replace('_', ' ', ucwords($state)),
                    ])->render())
                    ->html(),

                TextColumn::make('next_action')
                    ->label('Next Action')
                    ->state(fn (Shipment $record): string => match ($record->status) {
                        'Ready to Ship' => 'Pack & ship',
                        'Shipped' => 'In transit',
                        'Delivered' => 'Delivered ✓',
                        default => 'Review',
                    })
                    ->badge()
                    ->color(fn (Shipment $record): string => match ($record->status) {
                        'Ready to Ship' => 'warning',
                        'Shipped' => 'info',
                        'Delivered' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('item_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('weight_oz')
                    ->label('Weight (oz)')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 1) : '—')
                    ->sortable(),

                TextColumn::make('dimensions_json')
                    ->label('Dimensions')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '—';
                        $dims = $state;
                        return sprintf(
                            "%s × %s × %s in",
                            $dims['length_in'] ?? '?',
                            $dims['width_in'] ?? '?',
                            $dims['height_in'] ?? '?'
                        );
                    }),

                TextColumn::make('shipping_cost')
                    ->label('Shipping Cost')
                    ->money('USD')
                    ->sortable(),

                IconColumn::make('insurance_added')
                    ->label('Insurance')
                    ->boolean(),

                IconColumn::make('signature_required')
                    ->label('Signature')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('show_id')
                    ->label('Show')
                    ->options(Show::orderBy('title')->pluck('title', 'id'))
                    ->searchable(),

                SelectFilter::make('carrier')
                    ->label('Carrier')
                    ->options(Shipment::distinct('carrier')->pluck('carrier', 'carrier')->toArray()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Ready to Ship' => 'Ready to Ship',
                        'Shipped' => 'Shipped',
                        'Delivered' => 'Delivered',
                    ]),

                SelectFilter::make('insurance_added')
                    ->label('Insurance Added')
                    ->options([
                        true => 'Yes',
                        false => 'No',
                    ]),

                SelectFilter::make('signature_required')
                    ->label('Signature Required')
                    ->options([
                        true => 'Yes',
                        false => 'No',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([50, 100, 200])
            ->striped()
            ->hover();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
        ];
    }
}
