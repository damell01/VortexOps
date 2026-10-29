<?php

namespace App\Filament\Resources\StreamerResource\Pages;

use App\Filament\Resources\StreamerResource;
use App\Support\PaymentStructure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

            Action::make('transfer_duplicate_shows')
                ->label('Transfer Duplicate Shows')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->visible(fn (): bool => (bool) (auth()->user()?->isOwner() || auth()->user()?->isAdmin()) && $this->record->isStreamer())
                ->modalHeading('Transfer show assignments to the correct profile')
                ->modalDescription(fn (): string => 'Source: '.$this->record->name.' (ID '.$this->record->id.'). This transfers show assignments only. Payouts, reports, loans, logins and historical payroll remain unchanged. The source profile will be marked inactive, not deleted.')
                ->form([
                    Select::make('target_id')
                        ->label('Keep this streamer profile')
                        ->options(fn (): array => \App\Models\Streamer::query()
                            ->streamers()->where('status', 'active')
                            ->whereKeyNot($this->record->id)
                            ->where('business_id', $this->record->business_id)
                            ->orderBy('name')->get()
                            ->mapWithKeys(fn ($p) => [$p->id => $p->name.' (#'.$p->id.')'])->all())
                        ->searchable()->required()
                        ->helperText('Only active streamer profiles in the same business are eligible.'),
                ])
                ->requiresConfirmation()
                ->modalSubmitActionLabel('Transfer shows and deactivate duplicate')
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->isOwner() || auth()->user()?->isAdmin(), 403);
                    $sourceId = (int) $this->record->id;
                    $targetId = (int) ($data['target_id'] ?? 0);
                    if ($sourceId === $targetId) {
                        throw ValidationException::withMessages(['target_id' => 'Choose a different profile.']);
                    }

                    $moved = DB::transaction(function () use ($sourceId, $targetId): int {
                        $profiles = \App\Models\Streamer::query()
                            ->whereIn('id', [$sourceId, $targetId])
                            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                        $source = $profiles->get($sourceId);
                        $target = $profiles->get($targetId);
                        if (! $source || ! $target || ! $source->isStreamer() || ! $target->isStreamer()
                            || $source->status !== 'active' || $target->status !== 'active'
                            || (int) $source->business_id !== (int) $target->business_id) {
                            throw ValidationException::withMessages(['target_id' => 'Profiles must be active streamers in the same business.']);
                        }
                        $rows = DB::table('show_streamer')->where('streamer_id', $sourceId)
                            ->orderBy('show_id')->lockForUpdate()->get();
                        foreach ($rows as $row) {
                            $existing = DB::table('show_streamer')
                                ->where('show_id', $row->show_id)->where('streamer_id', $targetId)->first();
                            if ($existing) {
                                if ($row->is_primary && ! $existing->is_primary) {
                                    DB::table('show_streamer')->where('show_id', $row->show_id)
                                        ->where('streamer_id', $targetId)->update(['is_primary' => true, 'updated_at' => now()]);
                                }
                            } else {
                                DB::table('show_streamer')->insert([
                                    'show_id' => $row->show_id, 'streamer_id' => $targetId,
                                    'is_primary' => $row->is_primary,
                                    'created_at' => now(), 'updated_at' => now(),
                                ]);
                            }
                            DB::table('show_streamer')->where('show_id', $row->show_id)
                                ->where('streamer_id', $sourceId)->delete();
                        }
                        $source->update(['status' => 'inactive']);
                        return $rows->count();
                    });

                    activity('streamer_profile_transfer')
                        ->causedBy(auth()->user())
                        ->performedOn($this->record)
                        ->withProperties(['source_streamer_id' => $sourceId, 'target_streamer_id' => $targetId, 'show_assignments_transferred' => $moved])
                        ->log('Duplicate streamer show assignments transferred');
                    Notification::make()->title('Show assignments transferred')
                        ->body($moved.' show assignments moved. The duplicate is inactive; payroll and other linked records were not changed.')
                        ->success()->send();
                    $this->redirect(StreamerResource::getUrl('index'));
                }),

            DeleteAction::make(),
        ];
    }
}
