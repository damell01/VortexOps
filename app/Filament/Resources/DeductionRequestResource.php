<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeductionRequestResource\Pages;
use App\Models\DeductionRequest;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeductionRequestResource extends Resource
{
    protected static ?string $model = DeductionRequest::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Streams';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return 'Deduction Request';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Deduction Requests';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['show.title', 'streamer.name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->show->title ?? 'Show #' . $record->show_id) . ' — ' . ($record->streamer->name ?? '?');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Status' => DeductionRequest::statusLabels()[$record->status] ?? $record->status,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('show.title')
                    ->label('Show')
                    ->default('—')
                    ->searchable()
                    ->url(fn (DeductionRequest $record) => ShowResource::getUrl('view', ['record' => $record->show_id])),

                TextColumn::make('show.show_date')
                    ->label('Show Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => DeductionRequest::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'draft'     => 'gray',
                        'pending'   => 'warning',
                        'approved'  => 'info',
                        'processed' => 'success',
                        'rejected'  => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines'),

                TextColumn::make('total_cogs')
                    ->label('Total COGS')
                    ->getStateUsing(fn (DeductionRequest $record) => $record->totalCogs())
                    ->money('USD'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DeductionRequest::statusLabels()),

                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->relationship('streamer', 'name'),
            ])
            ->actions([
                ViewAction::make()->label('Review'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeductionRequests::route('/'),
            'view'  => Pages\ViewDeductionRequest::route('/{record}'),
        ];
    }
}
