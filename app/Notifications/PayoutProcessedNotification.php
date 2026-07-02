<?php

namespace App\Notifications;

use App\Models\Payout;
use Illuminate\Notifications\Notification;

class PayoutProcessedNotification extends Notification
{
    public function __construct(public readonly Payout $payout) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $streamer = $this->payout->streamer?->name ?? 'Unknown streamer';
        $amount   = '$' . number_format((float) $this->payout->calculated_payout, 2);

        return [
            'title'      => 'Payout Processed',
            'body'       => "Payout of {$amount} for {$streamer} has been marked as paid.",
            'payout_id'  => $this->payout->id,
            'show_id'    => $this->payout->show_id,
            'icon'       => 'heroicon-o-currency-dollar',
            'color'      => 'success',
        ];
    }
}
