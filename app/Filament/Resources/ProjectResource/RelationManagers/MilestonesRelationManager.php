<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectMilestone;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),

            DatePicker::make('due_date')
                ->label('Due Date'),

            Select::make('status')
                ->options(ProjectMilestone::statusLabels())
                ->default('pending')
                ->required()
                ->live(),

            TextInput::make('sort_order')
                ->label('Order')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('status')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'completed'   => '✓',
                        'in_progress' => '▶',
                        default       => '○',
                    })
                    ->color(fn ($state) => match ($state) {
                        'completed'   => 'success',
                        'in_progress' => 'warning',
                        default       => 'gray',
                    })
                    ->label('')
                    ->width('32px'),

                TextColumn::make('title')
                    ->searchable()
                    ->weight(fn (ProjectMilestone $r) => $r->status === 'completed' ? 'normal' : 'medium')
                    ->color(fn (ProjectMilestone $r) => $r->status === 'completed' ? 'gray' : null)
                    ->description(fn (ProjectMilestone $r) => $r->description ? \Illuminate\Support\Str::limit($r->description, 60) : null),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProjectMilestone::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'completed'   => 'success',
                        'in_progress' => 'warning',
                        default       => 'gray',
                    })
                    ->label('Status'),

                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('M j, Y')
                    ->color(fn (ProjectMilestone $r) =>
                        $r->due_date?->isPast() && $r->status !== 'completed' ? 'danger' : null
                    )
                    ->default('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add Milestone'),
            ])
            ->actions([
                Action::make('complete')
                    ->label('Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ProjectMilestone $r) => $r->status !== 'completed')
                    ->action(function (ProjectMilestone $r) {
                        $r->update(['status' => 'completed', 'completed_at' => now()]);
                        Notification::make()->title('Milestone completed')->success()->send();
                    }),

                Action::make('reopen')
                    ->label('Re-open')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (ProjectMilestone $r) => $r->status === 'completed')
                    ->action(fn (ProjectMilestone $r) => $r->update(['status' => 'pending', 'completed_at' => null])),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
