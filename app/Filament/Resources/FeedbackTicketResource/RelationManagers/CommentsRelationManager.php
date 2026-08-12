<?php

namespace App\Filament\Resources\FeedbackTicketResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // No "posted as" picker — comments are always attributed to
            // whoever's actually posting (set in mutateFormDataUsing below),
            // not user-selectable.
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
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->visible(fn () => auth()->user()?->canManageFeedback() ?? false),
            ]);
    }
}
