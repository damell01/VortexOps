<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectUpdate;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options(ProjectUpdate::typeLabels())
                ->default('update')
                ->required(),

            Select::make('user_id')
                ->label('Author')
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->default(auth()->id())
                ->searchable(),

            Textarea::make('body')
                ->label('Content')
                ->required()
                ->rows(3)
                ->columnSpanFull(),

            Toggle::make('is_resolved')
                ->label('Resolved')
                ->visible(fn ($get) => $get('type') === 'blocker'),
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
                        'feature'  => 'info',
                        'decision' => 'warning',
                        default    => 'gray',
                    })
                    ->width('100px'),

                TextColumn::make('body')
                    ->limit(120)
                    ->wrap()
                    ->description(fn (ProjectUpdate $r) => $r->is_resolved ? '✓ Resolved' : null),

                TextColumn::make('author.name')
                    ->label('By')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ProjectUpdate::typeLabels()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Entry')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] ??= auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ProjectUpdate $r) => $r->type === 'blocker' && ! $r->is_resolved)
                    ->action(function (ProjectUpdate $r) {
                        $r->update(['is_resolved' => true, 'resolved_at' => now()]);
                        Notification::make()->title('Blocker resolved')->success()->send();
                    }),

                Action::make('mark_done')
                    ->label('Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ProjectUpdate $r) => $r->type === 'feature' && ! $r->is_resolved)
                    ->action(function (ProjectUpdate $r) {
                        $r->update(['is_resolved' => true, 'resolved_at' => now()]);
                        Notification::make()->title('Feature marked done')->success()->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
