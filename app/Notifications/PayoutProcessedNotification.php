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

    protected string $event = 'payout_processed';

    public function toMail(object $notifiable): MailMessage
    {
        $streamer = $this->payout->streamer?->name;
        $mail = (new MailMessage)
            ->subject('A payout has been processed')
            ->line('A payout of $' . number_format((float) $this->payout->calculated_payout, 2) . ($streamer ? " for {$streamer}" : '') . ' has been processed.');

        // A payout from a pay run may not belong to one show.
        return $this->payout->show_id
            ? $mail->action('Open show', NotificationLinks::forShow((int) $this->payout->show_id, $notifiable))
            : $mail->action('Open VortexOps', url('/admin'));
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
