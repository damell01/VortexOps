<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ReviewSessionResource\Pages;
use App\Models\Project;
use App\Models\ReviewSession;
use App\Support\AdminModules;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReviewSessionResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reviews';

    protected static ?string $model = ReviewSession::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reviews');
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getNavigationLabel(): string
    {
        return 'Review Sessions';
    }

    public static function getModelLabel(): string
    {
        return 'Review Session';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('project_id')
                    ->label('Project')
                    ->options(Project::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Sprint 3 Review, V1 Client Walkthrough'),
                Select::make('status')
                    ->options(ReviewSession::statusLabels())
                    ->required()
                    ->default('open'),
            ]),
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['project', 'createdBy']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ReviewSession::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'open'      => 'success',
                        'submitted' => 'warning',
                        'closed'    => 'gray',
                        default     => 'gray',
                    }),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->badge()
                    ->color('info'),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReviewSession::statusLabels()),
                SelectFilter::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'name'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReviewSessions::route('/'),
            'create' => Pages\CreateReviewSession::route('/create'),
            'view'   => Pages\ViewReviewSession::route('/{record}'),
            'edit'   => Pages\EditReviewSession::route('/{record}/edit'),
        ];
    }
}
