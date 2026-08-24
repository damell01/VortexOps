<?php

namespace App\Notifications;

use App\Models\Payout;
use App\Support\NotificationLinks;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutProcessedNotification extends Notification
{
    use EmailsWhenEnabled;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A payout has been processed')
            ->line("A payout of $" . number_format((float) $this->payout->calculated_payout, 2) . " has been processed.")
            ->action('Open payout', NotificationLinks::forShow($this->payout->show_id, $notifiable))
            ->line('You are receiving this because email notifications are switched on in Settings.');
    }

    public function __construct(public readonly Payout $payout) {}


    public function toDatabase(object $notifiable): array
    {
        $isStreamer = method_exists($notifiable, 'isStreamer')
            && $notifiable->isStreamer() && ! $notifiable->isAdmin();
        $streamer = $this->payout->streamer?->name ?? 'Unknown streamer';
        $amount   = '$' . number_format((float) $this->payout->calculated_payout, 2);

        $notification = FilamentNotification::make()
            ->title('Payout Processed')
            ->body($isStreamer
                ? "Your payout of {$amount} has been marked as paid."
                : "Payout of {$amount} for {$streamer} has been marked as paid.")
            ->icon('heroicon-o-currency-dollar')
            ->success();

        if ($this->payout->show_id) {
            $notification->actions([
                Action::make('open')
                    ->label('View show')
                    ->url(NotificationLinks::forShow($this->payout->show_id, $notifiable))
                    ->button()
                    ->markAsRead(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
