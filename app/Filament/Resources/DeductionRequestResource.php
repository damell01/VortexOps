<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\DeductionRequestResource\Pages;
use App\Models\DeductionRequest;
use App\Support\AdminModules;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class DeductionRequestResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'streams';

    protected static ?string $model = DeductionRequest::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return 'Pending Approval';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pending Approvals';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pending Approvals';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('nav_badge:deduction_requests_pending', 60, fn () =>
            static::getModel()::query()->where('status', 'pending')->count()
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['show', 'streamer'])
            ->withSum('lines', 'line_total');
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
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'approved' => 'info',
                        'processed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines'),

                TextColumn::make('lines_sum_line_total')
                    ->label('Total COGS')
                    ->money('USD')
                    ->placeholder('$0.00'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('show_id')
                    ->label('Show')
                    ->relationship('show', 'title')
                    ->searchable(),

                SelectFilter::make('status')
                    ->options(DeductionRequest::statusLabels()),

                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->relationship('streamer', 'name'),
            ])
            ->actions([
                ViewAction::make()->label('Review'),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->stackedOnMobile()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeductionRequests::route('/'),
            'view' => Pages\ViewDeductionRequest::route('/{record}'),
        ];
    }
}
