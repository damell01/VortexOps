<?php

namespace App\Notifications;

use App\Models\Show;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Illuminate\Notifications\Messages\MailMessage;
use App\Support\NotificationLinks;
use Illuminate\Notifications\Notification;

class ShowPendingApprovalNotification extends Notification
{
    use EmailsWhenEnabled;

    protected string $event = 'show_pending_approval';

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Show pending approval: {$this->show->title}")
            ->line("Show \"{$this->show->title}\" has been reviewed and is waiting for approval.")
            ->action('Open show', NotificationLinks::forShow($this->show->id, $notifiable));
    }

    public function __construct(public readonly Show $show) {}


    public function toDatabase(object $notifiable): array
    {
        $request = $this->show->latestDeductionRequest ?? $this->show->deductionRequests()->latest()->first();
        $lineCount = $request?->lines?->count() ?? 0;

        return [
            'title' => 'Show Ready for Approval',
            'body' => "Show \"{$this->show->title}\" is ready for approval with {$lineCount} mapped line(s).",
            'show_id' => $this->show->id,
            'deduction_request_id' => $request?->id,
            'icon' => 'heroicon-o-clipboard-document-check',
            'color' => 'warning',
        ];
    }
}
