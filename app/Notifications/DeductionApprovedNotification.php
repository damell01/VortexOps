<?php

namespace App\Notifications;

use App\Models\DeductionRequest;
use App\Support\NotificationLinks;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeductionApprovedNotification extends Notification
{
    use EmailsWhenEnabled;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Deduction approved')
            ->line('An inventory deduction request has been approved and posted.')
            ->line('You are receiving this because email notifications are switched on in Settings.');
    }

    public function __construct(public readonly DeductionRequest $deductionRequest) {}


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
