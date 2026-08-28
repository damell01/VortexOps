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
                ->label('Compensation Overrides')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->form([
                    CheckboxList::make('fields')
                        ->label('Fields this person should override')
                        ->options([
                            'payout_type' => 'Payout type',
                            'payout_cadence' => 'Pay Run cadence',
                            'payout_percentage' => 'Profit share / payout %',
                            'package_rate' => 'Package / flat rate',
                            'hourly_rate' => 'Hourly rate',
                            'pwe_rate' => 'PWE rate',
                            'label_rate' => 'Label rate',
                            'include_tips' => 'Include tips',
                            'custom_payout_formula' => 'Custom formula',
                            'burden_rate_type' => 'Burden type',
                            'burden_rate_value' => 'Burden value',
                        ])
                        ->columns(2)
                        ->helperText('Unchecked fields inherit the Streamer or Fulfillment Payment Structure. The values on this profile are used only for checked fields.'),
                ])
                ->fillForm(function (): array {
                    $fields = $this->record->compensation_override_fields;

                    // A legacy row has not opted into team defaults yet. Show
                    // every field checked so the modal accurately represents
                    // that its existing pay terms are currently authoritative.
                    return ['fields' => $fields ?? PaymentStructure::FIELDS];
                })
                ->action(function (array $data): void {
                    $this->record->update([
                        'compensation_override_fields' => array_values($data['fields'] ?? []),
                    ]);

                    $effective = PaymentStructure::resolve($this->record->fresh());
                    activity('payment_structure')
                        ->causedBy(auth()->user())
                        ->performedOn($this->record)
                        ->withProperties([
                            'override_fields' => $data['fields'] ?? [],
                            'effective' => $effective['effective'],
                        ])
                        ->log('Team member compensation overrides changed');

                    Notification::make()
                        ->title('Compensation inheritance updated')
                        ->body(empty($data['fields']) ? 'This team member now inherits the full team Payment Structure.' : 'Only the selected fields now override the team default.')
                        ->success()
                        ->send();
                }),

            Action::make('use_team_defaults')
                ->label('Use Team Defaults')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Remove every individual compensation override and inherit the current Streamer/Fulfillment Payment Structure. Historical finalized payouts will not change.')
                ->action(function (): void {
                    $this->record->update(['compensation_override_fields' => []]);
                    Notification::make()->title('Using team Payment Structure')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }
}
