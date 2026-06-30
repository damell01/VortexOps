<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectUpdate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Updates & Activity';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options(ProjectUpdate::typeLabels())
                ->default('update')
                ->required(),

            Textarea::make('body')
                ->label('Details')
                ->required()
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProjectUpdate::typeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'blocker'  => 'danger',
                        'feature'  => 'primary',
                        'decision' => 'warning',
                        default    => 'gray',
                    }),

                TextColumn::make('body')
                    ->label('Details')
                    ->wrap()
                    ->limit(120),

                TextColumn::make('author.name')
                    ->label('By')
                    ->placeholder('System'),

                TextColumn::make('created_at')
                    ->since()
                    ->label('When'),
            ])
            ->filters([
                SelectFilter::make('type')->options(ProjectUpdate::typeLabels()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->type, ['blocker', 'feature']) && ! $record->is_resolved)
                    ->action(fn ($record) => $record->update(['is_resolved' => true, 'resolved_at' => now()])),

                DeleteAction::make()->iconButton(),
            ]);
    }
}
