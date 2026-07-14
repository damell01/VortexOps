<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StreamerResource;
use App\Models\Streamer;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ActiveStreamersWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    protected static ?string $heading = 'Active Streamers';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Streamer::query()
                    ->where('status', 'active')
                    ->inChannelContext()
                    ->withCount('inventoryLocations')
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('payout_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::payoutTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'profit_share'   => 'success',
                        'package'        => 'info',
                        'hourly'         => 'warning',
                        'flat_rate'      => 'gray',
                        'pwe_labels'     => 'info',
                        'hybrid'         => 'primary',
                        'custom_formula' => 'primary',
                        default          => 'gray',
                    }),
                TextColumn::make('inventory_locations_count')
                    ->label('Locations'),
                IconColumn::make('include_tips')
                    ->boolean()
                    ->label('Tips'),
                TextColumn::make('email')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->deferLoading()
            ->recordUrl(fn ($record) => StreamerResource::getUrl('view', ['record' => $record]))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No active streamers')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
