<?php

namespace App\Filament\Resources\StreamerLoanResource\Pages;

use App\Filament\Resources\StreamerLoanResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStreamerLoan extends ViewRecord
{
    protected static string $resource = StreamerLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordPayment')->label('Record Payment')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn () => $this->record->status === 'active')
                ->form([
                    TextInput::make('amount')->label('Amount ($)')->numeric()->minValue(0.01)->required(),
                    DatePicker::make('payment_date')->label('Payment Date')->default(now())->maxDate(now())->required(),
                    Textarea::make('note')->label('Note (optional)')->rows(2),
                ])
                ->action(function (array $data): void {
                    $this->record->recordManualPayment((float) $data['amount'], (string) $data['payment_date'], $data['note'] ?? null);
                    Notification::make()->title('Loan payment recorded')->success()->send();
                }),
            EditAction::make(),
        ];
    }
}
