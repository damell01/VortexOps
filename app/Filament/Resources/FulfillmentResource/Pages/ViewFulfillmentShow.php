<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Show;
use App\Support\NavVisibility;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewFulfillmentShow extends ViewRecord
{
    protected static string $resource = FulfillmentResource::class;

    protected function resolveRecord(int|string $key): Show
    {
        return Show::with(['streamers', 'channel', 'streamerLogEntry', 'orders', 'fulfillmentUsers'])
            ->findOrFail($key);
    }

    private function readOnly(): bool
    {
        $user = auth()->user();

        return ! ($user?->isAdmin() || $user?->isOwner())
            && NavVisibility::isReadOnlyForUser(FulfillmentResource::class, $user);
    }

    protected function getHeaderActions(): array
    {
        /** @var Show $show */
        $show = $this->record;
        $log  = $show->streamerLogEntry;
        $isPweLabels = $show->relationLoaded('streamers')
            ? $show->streamers->first()?->payout_type === 'pwe_labels'
            : $show->primaryStreamer()?->payout_type === 'pwe_labels';

        return [
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
                ->label('Mark Fulfillment Reviewed')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $log?->needsFulfillmentReview() && ! $this->readOnly())
                ->requiresConfirmation()
                ->modalHeading('Confirm fulfillment review')
                ->modalDescription('Confirms the PWE and label counts above are correct for this show\'s payout.')
                ->action(function () use ($log): void {
                    $log?->update([
                        'fulfillment_reviewed_by' => auth()->id(),
                        'fulfillment_reviewed_at' => now(),
                    ]);

                    Notification::make()->title('Fulfillment review recorded')->success()->send();
                    $this->redirect(FulfillmentResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
