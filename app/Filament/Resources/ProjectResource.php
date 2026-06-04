<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers\MilestonesRelationManager;
use App\Filament\Resources\ProjectResource\RelationManagers\UpdatesRelationManager;
use App\Models\Project;
use App\Models\User;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Operations';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function getNavigationLabel(): string
    {
        return 'Projects';
    }

    public static function getModelLabel(): string
    {
        return 'Project';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Projects';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['owner', 'milestones']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project Details')->schema([
                Grid::make(2)->schema([
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
                        ->options(User::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    DatePicker::make('target_date')
                        ->label('Target Date')
                        ->nullable(),

                    ColorPicker::make('color')
                        ->label('Color Tag')
                        ->default('#6366f1'),
                ]),

                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->deferLoading()
            ->defaultSort('updated_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('color')
                    ->label('')
                    ->formatStateUsing(fn ($state) => '')
                    ->html()
                    ->extraAttributes(fn (Project $record) => [
                        'style' => "width:4px; padding:0; background:{$record->color}; border-radius:4px;",
                    ])
                    ->width('4px'),

                TextColumn::make('name')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (Project $r) => \Illuminate\Support\Str::limit($r->description ?? '', 80)),

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
                        'critical' => 'danger',
                        'high'     => 'warning',
                        'medium'   => 'info',
                        'low'      => 'gray',
                        default    => 'gray',
                    }),

                TextColumn::make('milestones_progress')
                    ->label('Progress')
                    ->getStateUsing(function (Project $record): string {
                        $total = $record->milestones->count();
                        $done  = $record->milestones->where('status', 'completed')->count();
                        return $total > 0 ? "{$done}/{$total}" : '—';
                    })
                    ->description(function (Project $record): string {
                        $total = $record->milestones->count();
                        $done  = $record->milestones->where('status', 'completed')->count();
                        return $total > 0 ? round($done / $total * 100) . '% complete' : 'No milestones';
                    }),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->default('Unassigned')
                    ->toggleable(),

                TextColumn::make('target_date')
                    ->label('Target')
                    ->date('M j, Y')
                    ->default('—')
                    ->color(fn (Project $r) => $r->target_date?->isPast() && $r->status !== 'completed' ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Project::statusLabels()),

                SelectFilter::make('priority')
                    ->options(Project::priorityLabels()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
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
