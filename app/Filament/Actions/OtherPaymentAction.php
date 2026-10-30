<?php

namespace App\Filament\Actions;

use App\Models\WeeklyPayoutBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
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
            ->mountUsing(function (Schema $schema, array $arguments) use ($batch): void {
                $payoutId = (int) ($arguments['payout_id'] ?? 0);
                $payout = $payoutId ? $batch->payouts()->whereKey($payoutId)->first() : null;
                $schema->fill([
                    'streamer_id' => $payout?->streamer_id,
                    'assign_show' => false,
                    'payout_id' => null,
                ]);
            })
            ->schema([
                Select::make('streamer_id')->label('Team member')
                    ->options(fn () => $batch->payouts()->with('streamer')->get()
                        ->filter(fn ($p) => $p->streamer_id && $p->streamer)
                        ->unique('streamer_id')
                        ->mapWithKeys(fn ($p) => [$p->streamer_id => $p->streamer->name])->all())
                    ->searchable()->required()->live(),
                Checkbox::make('assign_show')->label('Apply to a specific show')
                    ->helperText('Optional. Leave unchecked for a general payment to this person.')
                    ->default(false)->live(),
                Select::make('payout_id')->label('Show')
                    ->options(fn (Get $get) => $batch->payouts()
                        ->with('show')
                        ->where('streamer_id', (int) $get('streamer_id'))
                        ->whereNotNull('show_id')->where('payout_type', '!=', \App\Services\OtherPayService::TYPE)->get()
                        ->mapWithKeys(fn ($p) => [$p->id => ($p->show?->title ?? 'Show #'.$p->show_id)
                            . ' · payout #'.$p->id])->all())
                    ->searchable()
                    ->visible(fn (Get $get) => (bool) $get('assign_show'))
                    ->required(fn (Get $get) => (bool) $get('assign_show')),
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
                    $streamerId = (int) $data['streamer_id'];
                    $query = $run->payouts()->where('streamer_id', $streamerId);
                    if (! empty($data['assign_show'])) {
                        $query->whereKey((int) ($data['payout_id'] ?? 0))->whereNotNull('show_id');
                    } else {
                        // Prefer an existing non-show payroll line for general adjustments.
                        $general = (clone $query)->whereNull('show_id')->orderBy('id')->first();
                        $query->whereKey($general?->id ?? (clone $query)->orderBy('id')->value('id'));
                    }
                    $payout = $query->lockForUpdate()->firstOrFail();
                    $amount = round((float) $data['amount'], 2);
                    $description = trim((string) $data['description']);
                    if ($amount <= 0 || $description === '') {
                        throw ValidationException::withMessages(['amount' => 'Enter a positive amount and a description.']);
                    }
                    $previous = (float) $payout->calculated_payout;
                    $source = clone $payout;
                    if (empty($data['assign_show'])) { $source->show_id = null; }
                    $extra = app(\App\Services\OtherPayService::class)->add($source, $amount, $description);
                    activity('pay_run')->causedBy(auth()->user())->performedOn($payout)
                        ->withProperties(['pay_run_id' => $run->id, 'amount' => $amount, 'description' => $description, 'previous_amount' => $previous, 'streamer_id' => $streamerId, 'adjustment_payout_id' => $extra->id, 'show_selected' => ! empty($data['assign_show'])])
                        ->log('Other payment added');
                    $run->recalculateTotal();
                });
                Notification::make()->title('Other payment added')->success()->send();
            });
    }
}
