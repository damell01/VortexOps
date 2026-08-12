<?php

namespace App\Notifications;

use App\Models\Show;
use App\Support\NotificationLinks;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class ShowReadyNotification extends Notification
{
    public function __construct(public readonly Show $show) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

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
