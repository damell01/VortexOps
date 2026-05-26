<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectApproval;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    protected static ?string $title = 'Approvals';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('label')->required()->maxLength(255)->columnSpanFull(),
                Select::make('status')->options(ProjectApproval::statusLabels())->required()->default('pending'),
                DateTimePicker::make('requested_at'),
                Toggle::make('visible_to_client')->default(true),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                TextColumn::make('label')->searchable()->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProjectApproval::statusLabels()[$state] ?? $state),
                TextColumn::make('requested_at')->dateTime('M j, Y g:i A')->placeholder('—'),
                TextColumn::make('approved_at')->dateTime('M j, Y g:i A')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('requested_at', 'desc');
    }
}
