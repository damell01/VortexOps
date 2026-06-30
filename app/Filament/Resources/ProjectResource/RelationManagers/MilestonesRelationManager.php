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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $title = 'Milestones';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->rows(2)->columnSpanFull(),
            Select::make('status')
                ->options(ProjectMilestone::statusLabels())
                ->default('pending')
                ->required(),
            DatePicker::make('due_date')->nullable(),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->width('40px'),

                TextColumn::make('title')
                    ->description(fn ($record) => $record->description),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProjectMilestone::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'completed'   => 'success',
                        'in_progress' => 'warning',
                        default       => 'gray',
                    }),

                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('M j, Y')
                    ->color(fn ($record) => $record->due_date?->isPast() && $record->status !== 'completed' ? 'danger' : null)
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                Action::make('complete')
                    ->label('Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'completed')
                    ->action(fn ($record) => $record->update(['status' => 'completed', 'completed_at' => now()])),

                Action::make('reopen')
                    ->label('Re-open')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn ($record) => $record->status === 'completed')
                    ->action(fn ($record) => $record->update(['status' => 'pending', 'completed_at' => null])),

                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }
}
