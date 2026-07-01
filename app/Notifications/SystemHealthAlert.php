<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SystemHealthAlert extends Notification
{
    public function __construct(public readonly array $issues) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'  => 'System Health Alert',
            'body'   => implode(' · ', $this->issues),
            'issues' => $this->issues,
            'icon'   => 'heroicon-o-exclamation-triangle',
            'color'  => 'danger',
        ];
    }
}
