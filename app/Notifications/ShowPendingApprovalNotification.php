<?php

namespace App\Notifications;

use App\Models\Show;
use Illuminate\Notifications\Notification;

class ShowPendingApprovalNotification extends Notification
{
    public function __construct(public readonly Show $show) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $request = $this->show->latestDeductionRequest ?? $this->show->deductionRequests()->latest()->first();
        $lineCount = $request?->lines?->count() ?? 0;

        return [
            'title' => 'Show Ready for Approval',
            'body' => "Show \"{$this->show->title}\" finished AI mapping and is ready for approval with {$lineCount} mapped line(s).",
            'show_id' => $this->show->id,
            'deduction_request_id' => $request?->id,
            'icon' => 'heroicon-o-clipboard-document-check',
            'color' => 'warning',
        ];
    }
}
