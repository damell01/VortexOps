<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers\MilestonesRelationManager;
use App\Filament\Resources\ProjectResource\RelationManagers\UpdatesRelationManager;
use App\Models\Project;
use App\Models\User;
use App\Support\AdminModules;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProjectResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'projects';

    protected static ?string $model = Project::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('projects');
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function getModelLabel(): string
    {
        return 'Project';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Projects';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project Details')->columns(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('status')
                    ->options(Project::statusLabels())
                    ->default('planning')
                    ->required(),

                Select::make('priority')
                    ->options(Project::priorityLabels())
                    ->default('medium')
                    ->required(),

                Select::make('owner_id')
                    ->label('Owner')
                    ->options(User::pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                DatePicker::make('target_date')
                    ->label('Target Date')
                    ->nullable(),

                ColorPicker::make('color')
                    ->label('Color')
                    ->default('#6366f1'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label('')
                    ->width('4px'),

                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Project $record) => Str::limit($record->description ?? '', 60)),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Project::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'planning'  => 'gray',
                        'active'    => 'success',
                        'on_hold'   => 'warning',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Project::priorityLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'low'      => 'gray',
                        'medium'   => 'info',
                        'high'     => 'warning',
                        'critical' => 'danger',
                        default    => 'gray',
                    }),

                TextColumn::make('milestone_progress')
                    ->label('Milestones')
                    ->state(fn (Project $record) => $record->milestoneProgress())
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state)) {
                            return '—';
                        }

                        return "{$state['done']}/{$state['total']}";
                    }),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('—'),

                TextColumn::make('target_date')
                    ->label('Target')
                    ->date('M j, Y')
                    ->color(fn (Project $record) => $record->target_date?->isPast() && $record->status !== 'completed' ? 'danger' : null)
                    ->placeholder('—'),
            ])
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->deferLoading()
            ->actions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            MilestonesRelationManager::class,
            UpdatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view'   => Pages\ViewProject::route('/{record}'),
            'edit'   => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
