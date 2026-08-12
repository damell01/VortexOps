<?php

namespace App\Filament\Resources\ShowResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

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
                    ->color('info')
                    ->weight('semibold'),

                TextColumn::make('old_value')
                    ->label('Before')
                    ->limit(50)
                    ->tooltip(fn (string $state) => $state)
                    ->placeholder('—'),

                TextColumn::make('new_value')
                    ->label('After')
                    ->limit(50)
                    ->tooltip(fn (string $state) => $state)
                    ->weight('semibold')
                    ->color('success')
                    ->placeholder('—'),

                BadgeColumn::make('source')
                    ->label('Source')
                    ->colors([
                        'primary' => 'manual',
                        'warning' => 'whatnot_import',
                        'info' => 'cron',
                        'gray' => 'api',
                    ])
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),

                TextColumn::make('changed_by')
                    ->label('By')
                    ->limit(30)
                    ->placeholder('system'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->emptyStateHeading('No changes yet')
            ->emptyStateDescription('Changes will appear here when this show is updated.');
    }
}
