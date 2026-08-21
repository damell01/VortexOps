<?php

namespace App\Filament\Resources\ShowResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;

class ChangeLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'changeLogs';
    protected static ?string $title = 'Change History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('field_name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('field_name')
                    ->label('Field')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucwords($state)))
                    ->color(fn (string $state) => str_starts_with($state, 'shipment_') ? 'warning' : 'info')
                    ->weight('semibold'),

                TextColumn::make('old_value')
                    ->label('Before')
                    ->limit(70)
                    ->tooltip(fn (?string $state) => $state)
                    ->placeholder('—'),

                TextColumn::make('new_value')
                    ->label('After')
                    ->limit(70)
                    ->tooltip(fn (?string $state) => $state)
                    ->weight('semibold')
                    ->color('success')
                    ->placeholder('—'),

                BadgeColumn::make('source')
                    ->label('Source')
                    ->colors([
                        'primary' => 'manual',
                        'warning' => 'whatnot_import',
                        'info' => 'whatnot_spa_sync',
                        'success' => 'whatnot_shipment_import',
                        'gray' => 'api',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'whatnot_import', 'whatnot_spa_sync' => 'Whatnot Analytics',
                        'whatnot_shipment_import' => 'Whatnot Shipment',
                        default => str_replace('_', ' ', ucfirst($state)),
                    }),

                TextColumn::make('changed_by')
                    ->label('By')
                    ->limit(30)
                    ->placeholder('system'),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->options([
                        'whatnot_import' => 'Whatnot Analytics',
                        'whatnot_spa_sync' => 'Whatnot Analytics (SPA)',
                        'whatnot_shipment_import' => 'Whatnot Shipment',
                        'manual' => 'Manual',
                    ]),
            ])
            ->emptyStateHeading('No changes yet')
            ->emptyStateDescription('Analytics, shipment, and manual changes will appear here when values change.');
    }
}
