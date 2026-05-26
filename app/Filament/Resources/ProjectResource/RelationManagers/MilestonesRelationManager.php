<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectMilestone;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $title = 'Milestones';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Select::make('status')->options(ProjectMilestone::statusLabels())->required()->default('not_started'),
                TextInput::make('sort_order')->numeric()->default(0),
                DatePicker::make('due_date'),
                Toggle::make('visible_to_client')->default(true),
                Textarea::make('description')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                TextColumn::make('title')->searchable()->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProjectMilestone::statusLabels()[$state] ?? $state),
                TextColumn::make('due_date')->date('M j, Y')->placeholder('—'),
                TextColumn::make('visible_to_client')->label('Client Visible')->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
            ])
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort_order');
    }
}
