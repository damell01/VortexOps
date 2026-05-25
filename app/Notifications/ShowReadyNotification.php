<?php

namespace App\Notifications;

use App\Models\Show;
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
        return [
            'title'   => 'Show Ready for Review',
            'body'    => "Show \"{$this->show->title}\" is ready for streamer assignment and AI mapping.",
            'show_id' => $this->show->id,
            'icon'    => 'heroicon-o-video-camera',
            'color'   => 'info',
        ];
    }
}
