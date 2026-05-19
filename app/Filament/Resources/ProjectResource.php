<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers\ApprovalsRelationManager;
use App\Filament\Resources\ProjectResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\ProjectResource\RelationManagers\MilestonesRelationManager;
use App\Filament\Resources\ProjectResource\RelationManagers\ReviewSessionsRelationManager;
use App\Filament\Resources\ProjectResource\RelationManagers\StatusUpdatesRelationManager;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-briefcase';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Project Hub';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return 'Project Hub';
    }

    public static function canCreate(): bool
    {
        return static::$model::query()->count() === 0;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'reviewSessions',
                'reviewItems as open_review_items_count' => fn (Builder $query) => $query->whereIn('status', ['open', 'in_progress']),
                'approvals as pending_approvals_count' => fn (Builder $query) => $query->where('status', 'pending'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project Overview')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options(Project::statusLabels())
                            ->required()
                            ->default('planning'),
                        TextInput::make('phase')
                            ->placeholder('Testing, Launch Prep, Homepage QA'),
                        TextInput::make('progress_percent')
                            ->label('Progress %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0),
                        DatePicker::make('launch_date')
                            ->label('Launch ETA'),
                        Select::make('owner_user_id')
                            ->label('Client Owner')
                            ->options(User::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                        Select::make('manager_user_id')
                            ->label('Project Manager')
                            ->options(User::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                        Toggle::make('is_active')
                            ->default(true),
                        Toggle::make('client_visible')
                            ->default(true),
                    ]),
                    Textarea::make('summary')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('High-level project summary for the client and internal team.'),
                    Textarea::make('current_focus')
                        ->rows(4)
                        ->columnSpanFull()
                        ->placeholder("- Mobile responsiveness\n- Stripe ACH integration\n- Driver dashboard cleanup"),
                    Textarea::make('client_needs')
                        ->rows(4)
                        ->columnSpanFull()
                        ->placeholder("- Upload final logo\n- Approve homepage hero\n- Provide SMTP credentials"),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Project::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'planning' => 'gray',
                        'implementation' => 'info',
                        'review' => 'warning',
                        'blocked' => 'danger',
                        'ready_to_launch' => 'success',
                        'launched' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('phase')
                    ->placeholder('—'),
                TextColumn::make('progress_percent')
                    ->label('Progress')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('open_review_items_count')
                    ->label('Open Feedback')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('pending_approvals_count')
                    ->label('Pending Approvals')
                    ->badge()
                    ->color('info'),
                TextColumn::make('launch_date')
                    ->label('Launch ETA')
                    ->date('M j, Y')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            MilestonesRelationManager::class,
            ApprovalsRelationManager::class,
            StatusUpdatesRelationManager::class,
            CommentsRelationManager::class,
            ReviewSessionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
