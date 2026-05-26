<?php

namespace App\Filament\Resources\StreamerResource\RelationManagers;

use App\Models\StreamerLoan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
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
            ->deferLoading()
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
                        if (! isset($data['remaining_balance']) || $data['remaining_balance'] === null) {
                            $data['remaining_balance'] = $data['original_amount'];
                        }
                        return $data;
                    }),
            ])
            ->actions([
                Action::make('record_payment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->color('success')
                    ->visible(fn (StreamerLoan $record) => $record->status === 'active')
                    ->form([
                        TextInput::make('amount')
                            ->label('Payment Amount ($)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        Textarea::make('note')
                            ->label('Note (optional)')
                            ->rows(2),
                    ])
                    ->action(function (StreamerLoan $record, array $data): void {
                        $amount     = (float) $data['amount'];
                        $newBalance = max(0, round((float) $record->remaining_balance - $amount, 2));

                        $noteAppend = $data['note']
                            ? " | Payment of \${$amount} recorded: {$data['note']}"
                            : " | Payment of \${$amount} recorded manually";

                        $record->update([
                            'remaining_balance' => $newBalance,
                            'status'            => $newBalance <= 0 ? 'paid_off' : 'active',
                            'notes'             => ($record->notes ?? '') . $noteAppend,
                        ]);

                        Notification::make()
                            ->title('Payment recorded — balance updated to $' . number_format($newBalance, 2))
                            ->success()
                            ->send();
                    }),

                Action::make('adjust_balance')
                    ->label('Adjust Balance')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form([
                        Select::make('direction')
                            ->label('Adjustment Type')
                            ->options([
                                'increase' => '↑ Increase (loan amount went up)',
                                'decrease' => '↓ Decrease (payment or correction)',
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('amount')
                            ->label('Amount ($)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2)
                            ->required(),
                    ])
                    ->action(function (StreamerLoan $record, array $data): void {
                        $amount  = (float) $data['amount'];
                        $current = (float) $record->remaining_balance;

                        $newBalance = $data['direction'] === 'increase'
                            ? round($current + $amount, 2)
                            : max(0, round($current - $amount, 2));

                        $direction  = $data['direction'] === 'increase' ? '+' : '-';
                        $noteAppend = " | Balance adjusted {$direction}\${$amount}: {$data['reason']}";

                        $record->update([
                            'remaining_balance' => $newBalance,
                            'status'            => $newBalance <= 0 ? 'paid_off' : 'active',
                            'notes'             => ($record->notes ?? '') . $noteAppend,
                        ]);

                        Notification::make()
                            ->title('Balance adjusted to $' . number_format($newBalance, 2))
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
