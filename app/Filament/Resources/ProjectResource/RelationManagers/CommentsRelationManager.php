<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Project Conversation';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('Comment')
                ->rows(4)
                ->required()
                ->maxLength(5000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                TextColumn::make('user.name')
                    ->label('Author')
                    ->placeholder('Unknown')
                    ->weight('bold'),
                TextColumn::make('body')
                    ->wrap()
                    ->limit(160),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['user_id' => auth()->id()]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
