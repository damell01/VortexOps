<?php

namespace App\Notifications;

use App\Models\Show;
use Illuminate\Notifications\Notification;

class ShowPendingReviewNotification extends Notification
{
    public function __construct(public readonly Show $show) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'   => 'Show Pending Review',
            'body'    => "Show \"{$this->show->title}\" has entered Pending Review and is ready for AI mapping or manual assignment.",
            'show_id' => $this->show->id,
            'icon'    => 'heroicon-o-clock',
            'color'   => 'warning',
        ];
    }
}
