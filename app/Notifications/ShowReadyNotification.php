<?php

namespace App\Notifications;

use App\Models\Show;
use App\Support\NotificationLinks;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowReadyNotification extends Notification
{
    use EmailsWhenEnabled;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Show ready for review: {$this->show->title}")
            ->line("Show \"{$this->show->title}\" is ready for streamer assignment and item mapping.")
            ->action('Open show', NotificationLinks::forShow($this->show->id, $notifiable))
            ->line('You are receiving this because email notifications are switched on in Settings.');
    }

    public function __construct(public readonly Show $show) {}


    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Show Ready for Review')
            ->body("Show \"{$this->show->title}\" is ready for streamer assignment and item mapping.")
            ->icon('heroicon-o-video-camera')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('Open show')
                    ->url(NotificationLinks::forShow($this->show->id, $notifiable))
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
