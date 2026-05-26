<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Filament\Resources\ReviewSessionResource;
use App\Models\ReviewSession;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviewSessions';

    protected static ?string $title = 'Feedback Sessions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            Select::make('status')
                ->options(ReviewSession::statusLabels())
                ->required()
                ->default('open'),
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
                    ->formatStateUsing(fn ($state) => ReviewSession::statusLabels()[$state] ?? $state),
                TextColumn::make('items_count')->counts('items')->label('Items'),
                TextColumn::make('created_at')->dateTime('M j, Y g:i A'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()]),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (ReviewSession $record) => ReviewSessionResource::getUrl('view', ['record' => $record])),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
