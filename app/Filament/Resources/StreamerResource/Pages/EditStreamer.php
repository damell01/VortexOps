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
                ->label('Streamer Pay Overrides')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->form([
                    CheckboxList::make('fields')
                        ->label('Only override what is different for this person')
                        ->options([
                            'payout_type' => 'Calculation type',
                            'payout_cadence' => 'Pay Run cadence',
                            'payout_percentage' => 'Streamer pay %',
                            'package_rate' => 'Package / flat rate',
                            'hourly_rate' => 'Hourly rate',
                            'pwe_rate' => 'PWE rate',
                            'label_rate' => 'Label rate',
                            'include_tips' => 'Include tips',
                            'custom_payout_formula' => 'Custom calculation formula',
                            'burden_rate_type' => 'Burden type',
                            'burden_rate_value' => 'Burden value',
                        ])
                        ->columns(2)
                        ->helperText('Streamers use the standard spreadsheet-based team calculation by default. Check a field only when this person needs a different value or formula. Fulfillment members continue to inherit the fulfillment structure.'),
                ])
                ->fillForm(function (): array {
                    $fields = $this->record->compensation_override_fields;

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
                        ->title('Streamer pay inheritance updated')
                        ->body(empty($data['fields']) ? 'This team member now inherits the full team calculation.' : 'Only the selected fields now override the team calculation.')
                        ->success()
                        ->send();
                }),

            Action::make('use_team_defaults')
                ->label('Use Team Calculation')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Remove every individual override and use the standard team calculation. Historical finalized payouts will not change.')
                ->action(function (): void {
                    $this->record->update(['compensation_override_fields' => []]);
                    Notification::make()->title('Using team calculation')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }
}
