<?php

namespace App\Notifications;

use App\Models\DeductionRequest;
use App\Support\NotificationLinks;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class DeductionApprovedNotification extends Notification
{
    public function __construct(public readonly DeductionRequest $deductionRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $show  = $this->deductionRequest->show?->title ?? 'Show #' . $this->deductionRequest->show_id;
        $total = '$' . number_format($this->deductionRequest->totalCogs(), 2);

        $notification = FilamentNotification::make()
            ->title('Deduction Approved')
            ->body("Deduction request for \"{$show}\" approved. COGS: {$total}.")
            ->icon('heroicon-o-clipboard-document-check')
            ->success();

        if ($this->deductionRequest->show_id) {
            $notification->actions([
                Action::make('open')
                    ->label('View show')
                    ->url(NotificationLinks::forShow($this->deductionRequest->show_id, $notifiable))
                    ->button()
                    ->markAsRead(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
