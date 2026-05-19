<?php

namespace App\Notifications;

use App\Models\Show;
use Illuminate\Notifications\Notification;

class ShowReconciledNotification extends Notification
{
    public function __construct(public readonly Show $show) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'   => 'Show Reconciled',
            'body'    => "Show \"{$this->show->title}\" has been reconciled and inventory deductions processed.",
            'show_id' => $this->show->id,
            'icon'    => 'heroicon-o-check-circle',
            'color'   => 'success',
        ];
    }
}
