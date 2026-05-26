<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ReviewItemResource\Pages;
use App\Models\Project;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\Facades\Cache;

class ReviewItemResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reviews';

    protected static ?string $model = ReviewItem::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reviews');
    }

    public static function getNavigationSort(): ?int
    {
        return 11;
    }

    public static function getNavigationLabel(): string
    {
        return 'Review Items';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('nav_badge:review_items_open', 60, fn () =>
            parent::getEloquentQuery()->where('status', 'open')->count()
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

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (auth()->user()?->isSuperAdmin()) {
            return true;
        }
        return $record->created_by === auth()->id();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['session', 'createdBy', 'assignedTo', 'comments.user']);

        if (! (auth()->user()?->isSuperAdmin() ?? false)) {
            $query->where('created_by', auth()->id());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Review Item')->schema([
                Grid::make(2)->schema([
                    \Filament\Forms\Components\Placeholder::make('session')
                        ->label('Session')
                        ->content(fn (ReviewItem $record): string => $record->session?->title ?? '—'),

                    \Filament\Forms\Components\Placeholder::make('page')
                        ->label('Page')
                        ->content(fn (ReviewItem $record): string => $record->page_title ?? $record->page_url),

                    \Filament\Forms\Components\Placeholder::make('type_label')
                        ->label('Type')
                        ->content(fn (ReviewItem $record): string => ReviewItem::typeLabels()[$record->type] ?? $record->type),

                    \Filament\Forms\Components\Placeholder::make('reporter')
                        ->label('Reported By')
                        ->content(fn (ReviewItem $record): string => $record->createdBy?->name ?? '—'),

                    Select::make('status')
                        ->options(ReviewItem::statusLabels())
                        ->required()
                        ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false),

                    \Filament\Forms\Components\Placeholder::make('status_display')
                        ->label('Status')
                        ->content(fn (ReviewItem $record): string => ReviewItem::statusLabels()[$record->status] ?? $record->status)
                        ->visible(fn () => ! (auth()->user()?->isSuperAdmin() ?? false)),

                    Select::make('priority')
                        ->options(ReviewItem::priorityLabels())
                        ->required()
                        ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false),

                    \Filament\Forms\Components\Placeholder::make('priority_display')
                        ->label('Priority')
                        ->content(fn (ReviewItem $record): string => ReviewItem::priorityLabels()[$record->priority] ?? $record->priority)
                        ->visible(fn () => ! (auth()->user()?->isSuperAdmin() ?? false)),

                    Select::make('assigned_to')
                        ->label('Assigned To')
                        ->options(User::pluck('name', 'id'))
                        ->nullable()
                        ->columnSpanFull()
                        ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false),
                ]),

                \Filament\Forms\Components\Placeholder::make('comment_text')
                    ->label('Comment')
                    ->content(fn (ReviewItem $record): string => $record->comment ?: '—')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        return $table
            ->deferLoading()
            ->columns(array_filter([
                ImageColumn::make('screenshot')
                    ->label('Shot')
                    ->square()
                    ->size(56)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('id')->label('#')->sortable(),

                $isSuperAdmin
                    ? TextColumn::make('session.project.name')->label('Project')->placeholder('—')->toggleable()
                    : null,

                $isSuperAdmin
                    ? TextColumn::make('session.title')->label('Session')->limit(28)
                    : null,

                TextColumn::make('page_title')
                    ->label('Page')
                    ->placeholder('—')
                    ->limit(36),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ReviewItem::typeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'bug'        => 'danger',
                        'suggestion' => 'info',
                        'question'   => 'warning',
                        default      => 'gray',
                    }),

                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ReviewItem::priorityLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'high'   => 'danger',
                        'normal' => 'warning',
                        'low'    => 'gray',
                        default  => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ReviewItem::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'open'        => 'danger',
                        'in_progress' => 'warning',
                        'fixed'       => 'success',
                        'approved'    => 'success',
                        'rejected'    => 'gray',
                        'wont_fix'    => 'gray',
                        default       => 'gray',
                    }),

                TextColumn::make('comment')->limit(55)->placeholder('—')->toggleable(),

                $isSuperAdmin
                    ? TextColumn::make('assignedTo.name')->label('Assigned')->placeholder('Unassigned')->toggleable()
                    : null,

                $isSuperAdmin
                    ? TextColumn::make('createdBy.name')->label('Reporter')->placeholder('—')->toggleable()
                    : null,

                TextColumn::make('created_at')->since()->sortable(),
            ]))
            ->filters(array_filter([
                SelectFilter::make('status')->options(ReviewItem::statusLabels()),
                SelectFilter::make('priority')->options(ReviewItem::priorityLabels()),
                SelectFilter::make('type')->options(ReviewItem::typeLabels()),

                $isSuperAdmin
                    ? SelectFilter::make('project_id')
                        ->label('Project')
                        ->query(function (Builder $query, array $data): Builder {
                            if (! filled($data['value'] ?? null)) {
                                return $query;
                            }

                            return $query->whereHas('session', fn (Builder $sessionQuery) => $sessionQuery->where('project_id', $data['value']));
                        })
                        ->options(Project::orderBy('name')->pluck('name', 'id'))
                    : null,

                $isSuperAdmin
                    ? SelectFilter::make('review_session_id')
                        ->label('Session')
                        ->relationship('session', 'title')
                    : null,
            ]))
            ->actions(array_filter([
                // Super-admin quick-status actions
                $isSuperAdmin
                    ? Action::make('mark_in_progress')
                        ->label('Start')
                        ->icon('heroicon-o-play')
                        ->color('warning')
                        ->visible(fn (ReviewItem $r) => $r->status === 'open')
                        ->action(fn (ReviewItem $r) => static::changeStatus($r, 'in_progress', 'Marked in progress'))
                    : null,

                $isSuperAdmin
                    ? Action::make('mark_fixed')
                        ->label('Fixed')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('success')
                        ->visible(fn (ReviewItem $r) => in_array($r->status, ['open', 'in_progress']))
                        ->action(fn (ReviewItem $r) => static::changeStatus($r, 'fixed', 'Marked as fixed'))
                    : null,

                $isSuperAdmin
                    ? Action::make('mark_approved')
                        ->label('Approve')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (ReviewItem $r) => in_array($r->status, ['open', 'fixed']))
                        ->action(fn (ReviewItem $r) => static::changeStatus($r, 'approved', 'Approved'))
                    : null,

                $isSuperAdmin
                    ? Action::make('mark_rejected')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->visible(fn (ReviewItem $r) => ! in_array($r->status, ['approved', 'rejected', 'wont_fix']))
                        ->requiresConfirmation()
                        ->action(fn (ReviewItem $r) => static::changeStatus($r, 'rejected', 'Rejected'))
                    : null,

                ViewAction::make(),

                $isSuperAdmin ? DeleteAction::make() : null,
            ]))
            ->defaultSort('created_at', 'desc');
    }

    private static function changeStatus(ReviewItem $record, string $status, string $message): void
    {
        $record->update(['status' => $status]);
        Notification::make()->title($message)->success()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviewItems::route('/'),
            'view'  => Pages\ViewReviewItem::route('/{record}'),
            'edit'  => Pages\EditReviewItem::route('/{record}/edit'),
        ];
    }
}
