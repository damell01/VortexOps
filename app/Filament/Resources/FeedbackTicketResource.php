<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\FeedbackTicketResource\Pages;
use App\Models\FeedbackTicket;
use App\Support\AdminModules;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class FeedbackTicketResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reviews';

    protected static ?string $model = FeedbackTicket::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chat-bubble-bottom-center-text';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reviews');
    }

    public static function getNavigationSort(): ?int
    {
        return 12;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('nav-badge:feedback-tickets:open', 30, fn (): int => FeedbackTicket::whereIn('status', ['open', 'in_progress'])->count());
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getModelLabel(): string
    {
        return 'Feedback Ticket';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Feedback';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['submitter', 'assignee']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn ($state) => FeedbackTicket::priorityLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'high'   => 'danger',
                        'medium' => 'warning',
                        'low'    => 'info',
                        default  => 'gray',
                    })
                    ->width('80px'),

                TextColumn::make('title')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => FeedbackTicket::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'open'        => 'danger',
                        'in_progress' => 'warning',
                        'resolved'    => 'success',
                        'closed'      => 'gray',
                        default       => 'gray',
                    }),

                TextColumn::make('submitted_name')
                    ->label('From')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('assignee.name')
                    ->label('Assigned To')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('page_url')
                    ->label('Page')
                    ->limit(35)
                    ->tooltip(fn (FeedbackTicket $record): ?string => $record->page_url)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, g:ia')
                    ->sortable(),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(FeedbackTicket::statusLabels()),

                SelectFilter::make('priority')
                    ->options(FeedbackTicket::priorityLabels()),
            ])
            ->actions([
                ViewAction::make()->label('Review')->iconButton(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedbackTickets::route('/'),
            'view'  => Pages\ViewFeedbackTicket::route('/{record}'),
        ];
    }
}
