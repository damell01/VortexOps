<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\FeedbackTicketResource\Pages;
use App\Filament\Resources\FeedbackTicketResource\RelationManagers\CommentsRelationManager;
use App\Models\FeedbackTicket;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeedbackTicketResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'operations';
    protected static ?string $model = FeedbackTicket::class;

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chat-bubble-bottom-center-text';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('operations');
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getNavigationLabel(): string
    {
        return 'Feedback';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $isManager = $user?->canManageFeedback() ?? false;
        $cacheKey = $isManager ? 'nav_badge:feedback_open' : "nav_badge:feedback_open_{$user?->id}";

        $count = Cache::remember($cacheKey, 60, function () use ($isManager, $user) {
            $query = FeedbackTicket::whereIn('status', ['open', 'in_progress', 'needs_info']);
            if (! $isManager) {
                $query->where('submitted_by', $user?->id);
            }
            return $query->count();
        });

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

    // Tickets can only come in via the feedback widget/controller, not created
    // here in the admin UI.
    public static function canCreate(): bool
    {
        return false;
    }

    // Open to any authenticated user (same gate as the feedback widget itself)
    // rather than the trait's admin-only default — submitters need to reach
    // their own ticket to comment on it. What each user actually sees within
    // the resource is then scoped by getEloquentQuery()/canView()/canEdit().
    protected static function passesModuleAccessCheck(): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['submitter', 'assignee']);

        $user = auth()->user();
        if (! $user?->canManageFeedback()) {
            $query->where('submitted_by', $user?->id);
        }

        return $query;
    }

    public static function canView(Model $record): bool
    {
        // A page granted on Roles & Permissions carries its records with it.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        return $user && ($user->canManageFeedback() || $record->submitted_by === $user->id);
    }

    public static function canEdit(Model $record): bool
    {
        // "Can Edit" on Roles & Permissions, which used to be a checkbox
        // nothing read.
        if (\App\Support\RoleAccess::allowsEditing(static::class)) {
            return true;
        }

        return auth()->user()?->canManageFeedback() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->canManageFeedback() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->canManageFeedback() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ticket Details')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    Select::make('status')
                        ->options(FeedbackTicket::statusLabels())
                        ->required(),

                    Select::make('priority')
                        ->options(FeedbackTicket::priorityLabels())
                        ->required(),

                    Select::make('type')
                        ->options(FeedbackTicket::typeLabels())
                        ->required(),

                    Select::make('assigned_to')
                        ->label('Assigned To')
                        ->options(User::orderBy('name')->pluck('name', 'id'))
                        ->nullable()
                        ->searchable(),
                ]),

                Textarea::make('admin_notes')
                    ->label('Internal Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->emptyStateHeading('No feedback yet')
            ->emptyStateDescription('Bug reports and feature requests from the team will appear here.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->deferLoading()
            ->striped()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('screenshot_path')
                    ->label('')
                    ->square()
                    ->size(48)
                    ->disk('public')
                    ->defaultImageUrl(null)
                    ->toggleable(),

                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('48px'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => FeedbackTicket::typeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'bug'        => 'danger',
                        'suggestion' => 'info',
                        'question'   => 'warning',
                        default      => 'gray',
                    })
                    ->width('90px'),

                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn ($state) => FeedbackTicket::priorityLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'high'   => 'danger',
                        'medium' => 'warning',
                        'low'    => 'gray',
                        default  => 'gray',
                    })
                    ->width('80px'),

                TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => FeedbackTicket::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),

                TextColumn::make('submitted_name')
                    ->label('From')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('assignee.name')
                    ->label('Assigned')
                    ->default('Unassigned')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(FeedbackTicket::statusLabels()),

                SelectFilter::make('priority')
                    ->options(FeedbackTicket::priorityLabels()),

                SelectFilter::make('type')
                    ->options(FeedbackTicket::typeLabels()),
            ])
            ->actions([
                Action::make('mark_in_progress')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (FeedbackTicket $r) => (auth()->user()?->canManageFeedback() ?? false) && $r->status === 'open')
                    ->action(function (FeedbackTicket $r) {
                        $r->update(['status' => 'in_progress']);
                        Cache::forget('nav_badge:feedback_open');
                        Notification::make()->title('Marked in progress')->success()->send();
                    }),

                Action::make('mark_resolved')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (FeedbackTicket $r) => (auth()->user()?->canManageFeedback() ?? false) && in_array($r->status, ['open', 'in_progress', 'needs_info']))
                    ->action(function (FeedbackTicket $r) {
                        $r->update(['status' => 'resolved', 'resolved_at' => now()]);
                        Cache::forget('nav_badge:feedback_open');
                        Notification::make()->title('Marked resolved')->success()->send();
                    }),

                Action::make('needs_info')
                    ->label('Needs Info')
                    ->icon('heroicon-o-question-mark-circle')
                    ->color('info')
                    ->visible(fn (FeedbackTicket $r) => (auth()->user()?->canManageFeedback() ?? false) && in_array($r->status, ['open', 'in_progress']))
                    ->action(function (FeedbackTicket $r) {
                        $r->update(['status' => 'needs_info']);
                        Cache::forget('nav_badge:feedback_open');
                        Notification::make()->title('Marked — needs more info')->warning()->send();
                    }),

                ViewAction::make()
                    ->size('sm')
                    ->iconButton(),
                EditAction::make()
                    ->size('sm')
                    ->iconButton()
                    ->visible(fn (FeedbackTicket $r) => static::canEdit($r)),
                DeleteAction::make()
                    ->visible(fn (FeedbackTicket $r) => static::canDelete($r)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => static::canDeleteAny()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedbackTickets::route('/'),
            'view'  => Pages\ViewFeedbackTicket::route('/{record}'),
            'edit'  => Pages\EditFeedbackTicket::route('/{record}/edit'),
        ];
    }
}
