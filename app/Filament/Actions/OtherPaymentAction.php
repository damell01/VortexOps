<?php

namespace App\Filament\Actions;

use App\Models\WeeklyPayoutBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OtherPaymentAction
{
    public static function make(WeeklyPayoutBatch $batch): Action
    {
        return Action::make('other_payment')
            ->label('Other Payment')
            ->icon('heroicon-o-plus-circle')
            ->visible(fn () => $batch->fresh()->status === 'draft' && $batch->payouts()->exists())
            ->schema([
                Select::make('payout_id')->label('Employee payout')
                    ->options(fn () => $batch->payouts()->with('streamer')->get()
                        ->mapWithKeys(fn ($p) => [$p->id => ($p->streamer?->name ?? 'Unknown').' #'.$p->id])->all())
                    ->searchable()->required(),
                TextInput::make('amount')->label('Additional amount')
                    ->prefix('$')->numeric()->minValue(0.01)->required(),
                Textarea::make('description')->label('Reason for payment')
                    ->required()->maxLength(500),
            ])
            ->action(function (array $data) use ($batch): void {
                DB::transaction(function () use ($batch, $data) {
                    $run = WeeklyPayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);
                    if ($run->status !== 'draft') {
                        throw new \RuntimeException('Only draft pay runs can be adjusted.');
                    }
                    $payout = $run->payouts()->whereKey((int) $data['payout_id'])->lockForUpdate()->firstOrFail();
                    $amount = round((float) $data['amount'], 2);
                    $description = trim((string) $data['description']);
                    if ($amount <= 0 || $description === '') {
                        throw ValidationException::withMessages(['amount' => 'Enter a positive amount and a description.']);
                    }
                    $previous = (float) $payout->calculated_payout;
                    $payout->update([
                        'calculated_payout' => round($previous + $amount, 2),
                        'calculation_notes' => trim(($payout->calculation_notes ?: '')."\n[OTHER_PAYMENT] +$".number_format($amount, 2).': '.$description),
                    ]);
                    activity('pay_run')->causedBy(auth()->user())->performedOn($payout)
                        ->withProperties(['pay_run_id' => $run->id, 'amount' => $amount, 'description' => $description, 'previous_amount' => $previous])
                        ->log('Other payment added');
                    $run->recalculateTotal();
                });
                Notification::make()->title('Other payment added')->success()->send();
            });
    }
}
