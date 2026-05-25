<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectStatusUpdate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusUpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusUpdates';

    protected static ?string $title = 'Status Updates';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Select::make('status')->options(ProjectStatusUpdate::statusLabels())->required()->default('note'),
                Toggle::make('visible_to_client')->default(true),
                Textarea::make('body')->rows(4)->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProjectStatusUpdate::statusLabels()[$state] ?? $state),
                TextColumn::make('body')->limit(60)->placeholder('—'),
                TextColumn::make('created_at')->dateTime('M j, Y g:i A')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()]),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('created_at', 'desc');
    }
}
