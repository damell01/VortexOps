<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\AiLogResource\Pages;
use App\Models\AiLog;
use App\Support\AdminModules;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Builder;

class AiLogResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'ai';

    protected static ?string $model = AiLog::class;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('ai');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function getModelLabel(): string
    {
        return 'AI Log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'AI Logs';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->columns(2)->schema([
                Placeholder::make('model')
                    ->content(fn (AiLog $record): string => $record->model),
                Placeholder::make('action_type')
                    ->label('Action Type')
                    ->content(fn (AiLog $record): string => $record->action_type ?? '—'),
                Placeholder::make('latency_ms')
                    ->label('Latency')
                    ->content(fn (AiLog $record): string => $record->latency_ms ? number_format($record->latency_ms / 1000, 2) . 's' : '—'),
                Placeholder::make('user')
                    ->content(fn (AiLog $record): string => $record->user?->name ?? '—'),
                Textarea::make('prompt')
                    ->columnSpanFull()
                    ->rows(5)
                    ->disabled(),
            ]),
            Section::make('Response')->schema([
                Placeholder::make('status')
                    ->content(fn (AiLog $record): string => $record->success ? 'Success' : 'Failed'),
                Textarea::make('response')
                    ->columnSpanFull()
                    ->rows(12)
                    ->disabled(),
                Textarea::make('error_message')
                    ->label('Error')
                    ->columnSpanFull()
                    ->rows(3)
                    ->disabled()
                    ->visible(fn (AiLog $record): bool => ! $record->success),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutEagerLoads()
                ->with(['user:id,name']))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('action_type')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'inventory_analysis' => 'info',
                        'reorder_suggestion' => 'warning',
                        'movement_analysis'  => 'primary',
                        'freeform_query'     => 'gray',
                        default              => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'inventory_analysis' => 'Inventory Analysis',
                        'reorder_suggestion' => 'Reorder Suggestion',
                        'movement_analysis'  => 'Movement Analysis',
                        'freeform_query'     => 'Freeform Query',
                        default              => $state ?? '—',
                    }),

                TextColumn::make('prompt')
                    ->limit(60)
                    ->tooltip(fn (AiLog $record): string => $record->prompt),

                TextColumn::make('model')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('success')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'OK' : 'Failed'),

                TextColumn::make('latency_ms')
                    ->label('Latency')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1000, 2) . 's' : '—')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->default('—'),

                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action_type')
                    ->options([
                        'inventory_analysis' => 'Inventory Analysis',
                        'reorder_suggestion' => 'Reorder Suggestion',
                        'movement_analysis'  => 'Movement Analysis',
                        'freeform_query'     => 'Freeform Query',
                    ]),
                TernaryFilter::make('success')
                    ->label('Status')
                    ->trueLabel('Successful')
                    ->falseLabel('Failed'),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('id', 'desc')
            ->recordAction(fn ($record) => 'view')
            ->actions([ViewAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiLogs::route('/'),
            'view'  => Pages\ViewAiLog::route('/{record}'),
        ];
    }
}
