<?php

namespace App\Notifications;

use App\Models\DeductionRequest;
use Illuminate\Notifications\Notification;

class DeductionApprovedNotification extends Notification
{
    public function __construct(public readonly DeductionRequest $deductionRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $show  = $this->deductionRequest->show?->title ?? 'Show #' . $this->deductionRequest->show_id;
        $total = '$' . number_format($this->deductionRequest->totalCogs(), 2);

        return [
            'title'                => 'Deduction Approved',
            'body'                 => "Deduction request for \"{$show}\" approved. COGS: {$total}.",
            'deduction_request_id' => $this->deductionRequest->id,
            'show_id'              => $this->deductionRequest->show_id,
            'icon'                 => 'heroicon-o-clipboard-document-check',
            'color'                => 'success',
        ];
    }
}
