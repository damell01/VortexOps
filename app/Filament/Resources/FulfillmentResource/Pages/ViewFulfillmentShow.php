<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Show;
use App\Models\User;
use App\Support\NavVisibility;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewFulfillmentShow extends ViewRecord
{
    protected static string $resource = FulfillmentResource::class;

    public function getView(): string
    {
        return 'filament.resources.fulfillment-resource.pages.view-fulfillment-show';
    }

    protected function resolveRecord(int|string $key): Show
    {
        return Show::with([
            'streamers',
            'channel',
            'streamerLogEntry.streamer',
            'streamerLogEntry.items.inventoryItem',
            'shipments',
            'fulfillmentUsers',
            'payouts.batch',
        ])->findOrFail($key);
    }

    private function readOnly(): bool
    {
        $user = auth()->user();

        return ! ($user?->isAdmin() || $user?->isOwner())
            && NavVisibility::isReadOnlyForUser(FulfillmentResource::class, $user);
    }

    private function needsCountVerification(): bool
    {
        $log = $this->record->streamerLogEntry;

        if (! $log) {
            return false;
        }

        $approved = $log->status === 'admin_approved' || $log->approval_status === 'approved';

        return $approved
            && $log->fulfillment_reviewed_at === null
            && ($log->streamer?->payout_type === 'pwe_labels');
    }

    protected function getHeaderActions(): array
    {
        /** @var Show $show */
        $show = $this->record;
        $log  = $show->streamerLogEntry;
        $user = auth()->user();
        $canAssign = (bool) ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillmentAdmin());
        $isPweLabels = $show->relationLoaded('streamers')
            ? $show->streamers->first()?->payout_type === 'pwe_labels'
            : $show->primaryStreamer()?->payout_type === 'pwe_labels';

        return [
            Action::make('assign_fulfillment')
                ->label($show->fulfillmentUsers->isEmpty() ? 'Assign Fulfillment' : 'Change Assignment')
                ->icon('heroicon-o-user-group')
                ->color($show->fulfillmentUsers->isEmpty() ? 'warning' : 'gray')
                ->visible(fn () => $canAssign)
                ->form([
                    Select::make('user_ids')
                        ->label('Fulfillment Team')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn () => User::query()
                            ->role(['fulfillment', 'fulfillment_admin'])
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn () => $show->fulfillmentUsers->pluck('id')->all())
                        ->helperText('Assign one or more people responsible for reviewing the streamer-logged items and fulfillment closeout for this show.'),
                ])
                ->action(function (array $data) use ($show): void {
                    $show->fulfillmentUsers()->sync($data['user_ids'] ?? []);
                    $show->load('fulfillmentUsers');
                    Notification::make()->title('Fulfillment assignment updated')->success()->send();
                }),

            Action::make('update_counts')
                ->label('Update PWE / Label Counts')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn () => $log !== null && $isPweLabels && ! $this->readOnly())
                ->form([
                    TextInput::make('pwe_count')
                        ->label('PWE Count')
                        ->integer()
                        ->minValue(0)
                        ->default(fn () => $log?->pwe_count),
                    TextInput::make('label_count')
                        ->label('Label-Only Count')
                        ->integer()
                        ->minValue(0)
                        ->default(fn () => $log?->label_count),
                ])
                ->action(function (array $data) use ($log): void {
                    $log?->update([
                        'pwe_count'   => $data['pwe_count'],
                        'label_count' => $data['label_count'],
                    ]);

                    Notification::make()->title('Counts updated')->success()->send();
                }),

            Action::make('mark_fulfillment_reviewed')
                ->label('Verify for Payroll')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->needsCountVerification() && ! $this->readOnly())
                ->requiresConfirmation()
                ->modalHeading('Verify fulfillment counts for payroll')
                ->modalDescription('Confirms the PWE and label counts are correct. The show can then move forward into payroll readiness once the logged-item review is complete.')
                ->action(function () use ($log): void {
                    $log?->update([
                        'fulfillment_reviewed_by' => auth()->id(),
                        'fulfillment_reviewed_at' => now(),
                    ]);

                    Notification::make()->title('Fulfillment verified for payroll')->success()->send();
                    $this->redirect(FulfillmentResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
