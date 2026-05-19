<?php

namespace App\Filament\Resources\StreamerResource\RelationManagers;

use App\Models\StreamerLoan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    protected static ?string $title = 'Loans & Advances';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('label')
                    ->label('Description')
                    ->placeholder('e.g. Equipment advance, Signing bonus')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('original_amount')
                    ->label('Original Amount ($)')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),

                TextInput::make('weekly_repayment')
                    ->label('Weekly Repayment ($)')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),

                TextInput::make('remaining_balance')
                    ->label('Remaining Balance ($)')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('Set to original amount when creating. Updates automatically when batches are finalized.'),

                Select::make('status')
                    ->options(StreamerLoan::statusLabels())
                    ->default('active')
                    ->required(),

                Toggle::make('deduct_from_payout')
                    ->label('Deduct from weekly payout')
                    ->helperText('On: deducted from calculated payout at finalization. Off: balance tracked, payout unchanged.')
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Description')
                    ->searchable(),

                TextColumn::make('original_amount')
                    ->label('Original')
                    ->money('USD'),

                TextColumn::make('weekly_repayment')
                    ->label('Weekly')
                    ->money('USD'),

                TextColumn::make('remaining_balance')
                    ->label('Remaining')
                    ->money('USD')
                    ->weight('bold')
                    ->color(fn ($record) => (float) $record->remaining_balance <= 0 ? 'success' : 'warning'),

                IconColumn::make('deduct_from_payout')
                    ->label('Deducted from Payout')
                    ->boolean(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => StreamerLoan::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'active'   => 'warning',
                        'paid_off' => 'success',
                        default    => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Default remaining_balance to original_amount if not set differently
                        if (! isset($data['remaining_balance']) || $data['remaining_balance'] === null) {
                            $data['remaining_balance'] = $data['original_amount'];
                        }
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
