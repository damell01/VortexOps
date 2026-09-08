<?php

namespace App\Filament\Resources\StreamerResource\Pages;

use App\Filament\Resources\StreamerResource;
use App\Support\PaymentStructure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStreamer extends EditRecord
{
    protected static string $resource = StreamerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compensation_overrides')
                ->label('Pay Adjustments')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->form([
                    CheckboxList::make('fields')
                        ->label('Only override what is different for this person')
                        ->options(fn (): array => $this->record->isFulfillment()
                            ? [
                                'payout_type' => 'Fulfillment pay method',
                                'payout_percentage' => 'Pay %',
                                'package_rate' => 'Package / flat rate',
                                'hourly_rate' => 'Hourly rate',
                                'pwe_rate' => 'PWE rate',
                                'label_rate' => 'Label rate',
                                'include_tips' => 'Include tips',
                                'custom_payout_formula' => 'Custom formula',
                                'burden_rate_type' => 'Burden type',
                                'burden_rate_value' => 'Burden value',
                            ]
                            : [
                                'payout_percentage' => 'Streamer pay %',
                                'include_tips' => 'Include tips',
                                'custom_payout_formula' => 'Custom calculation formula',
                            ])
                        ->columns(2)
                        ->helperText(fn (): string => $this->record->isFulfillment()
                            ? 'Unchecked values inherit the Fulfillment team structure.'
                            : 'Every streamer uses the same weekly team calculation. Select only the values that should be different for this person.'),
                ])
                ->fillForm(function (): array {
                    $resolved = PaymentStructure::resolve($this->record);
                    $allowed = $this->record->isFulfillment()
                        ? ['payout_type','payout_percentage','package_rate','hourly_rate','pwe_rate','label_rate','include_tips','custom_payout_formula','burden_rate_type','burden_rate_value']
                        : ['payout_percentage','include_tips','custom_payout_formula'];

                    return ['fields' => array_values(array_intersect($allowed, array_keys($resolved['overrides'] ?? [])))];
                })
                ->action(function (array $data): void {
                    $allowed = $this->record->isFulfillment()
                        ? ['payout_type','payout_percentage','package_rate','hourly_rate','pwe_rate','label_rate','include_tips','custom_payout_formula','burden_rate_type','burden_rate_value']
                        : ['payout_percentage','include_tips','custom_payout_formula'];
                    $fields = array_values(array_intersect($allowed, $data['fields'] ?? []));

                    $values = ['compensation_override_fields' => $fields];
                    if (! $this->record->isFulfillment()) {
                        $values['payout_type'] = 'profit_share';
                        $values['payout_cadence'] = 'weekly';
                    }
                    $this->record->update($values);

                    $effective = PaymentStructure::resolve($this->record->fresh());
                    activity('payment_structure')
                        ->causedBy(auth()->user())
                        ->performedOn($this->record)
                        ->withProperties([
                            'override_fields' => $fields,
                            'effective' => $effective['effective'],
                        ])
                        ->log('Team member pay adjustments changed');

                    Notification::make()
                        ->title('Pay adjustments updated')
                        ->body(empty($fields) ? 'This person now uses the full team default.' : 'Only the selected values differ from the team default.')
                        ->success()
                        ->send();
                }),

            Action::make('use_team_defaults')
                ->label('Use Team Calculation')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Remove every individual pay adjustment and use the standard team calculation. Historical finalized payouts will not change.')
                ->action(function (): void {
                    $values = ['compensation_override_fields' => []];
                    if (! $this->record->isFulfillment()) {
                        $values['payout_type'] = 'profit_share';
                        $values['payout_cadence'] = 'weekly';
                    }
                    $this->record->update($values);
                    Notification::make()->title('Using team calculation')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }
}
