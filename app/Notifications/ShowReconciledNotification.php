<?php

namespace App\Notifications;

use App\Models\Show;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowReconciledNotification extends Notification
{
    use EmailsWhenEnabled;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Show reconciled: {$this->show->title}")
            ->line("Show \"{$this->show->title}\" has been reconciled and its inventory deductions are posted.")
            ->action('Open show', NotificationLinks::forShow($this->show->id, $notifiable))
            ->line('You are receiving this because email notifications are switched on in Settings.');
    }

    public function __construct(public readonly Show $show) {}


    public function toDatabase(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('Show Reconciled')
            ->body("Show \"{$this->show->title}\" has been reconciled and inventory deductions processed.")
            ->icon('heroicon-o-check-circle')
            ->success()
            ->actions([
                \Filament\Actions\Action::make('open')
                    ->label('Open show')
                    ->url(\App\Support\NotificationLinks::forShow($this->show->id, $notifiable))
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
