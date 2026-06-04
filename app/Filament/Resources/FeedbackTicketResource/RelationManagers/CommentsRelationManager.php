<?php

namespace App\Filament\Resources\FeedbackTicketResource\RelationManagers;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Posted as')
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->default(auth()->id())
                ->required(),

            Textarea::make('body')
                ->label('Comment')
                ->required()
                ->rows(3)
                ->maxLength(2000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                TextColumn::make('user.name')
                    ->label('Author')
                    ->default('—')
                    ->weight('medium'),

                TextColumn::make('body')
                    ->label('Comment')
                    ->wrap()
                    ->limit(200),

                TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime('M j, g:ia')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] ??= auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ]);
    }
}
