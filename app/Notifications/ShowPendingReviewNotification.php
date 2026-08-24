<?php

namespace App\Notifications;

use App\Models\Show;
use App\Support\NotificationLinks;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowPendingReviewNotification extends Notification
{
    use EmailsWhenEnabled;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Show pending review: {$this->show->title}")
            ->line("Show \"{$this->show->title}\" is waiting on a review before it can move on.")
            ->action('Review show', NotificationLinks::forShow($this->show->id, $notifiable))
            ->line('You are receiving this because email notifications are switched on in Settings.');
    }

    public function __construct(public readonly Show $show) {}


    public function toDatabase(object $notifiable): array
    {
        $isStreamer = method_exists($notifiable, 'isStreamer')
            && $notifiable->isStreamer() && ! $notifiable->isAdmin();

        return FilamentNotification::make()
            ->title($isStreamer ? 'A show is ready for your items' : 'Show Pending Review')
            ->body($isStreamer
                ? "\"{$this->show->title}\" is ready — add the items you sold and their costs."
                : "Show \"{$this->show->title}\" has entered Pending Review and is ready for item mapping.")
            ->icon('heroicon-o-clipboard-document-list')
            ->warning()
            ->actions([
                Action::make('review')
                    ->label($isStreamer ? 'Add my items' : 'Open show')
                    ->url(NotificationLinks::forShow($this->show->id, $notifiable))
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
