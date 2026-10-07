<?php

namespace App\Notifications;

use App\Notifications\Concerns\EmailsWhenEnabled;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemHealthAlert extends Notification
{
    use EmailsWhenEnabled;

    protected string $event = 'system_health';

    public function __construct(public readonly array $issues) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(count($this->issues) === 1 ? 'System health issue detected' : count($this->issues) . ' system health issues detected')
            ->greeting('Something needs attention')
            ->line('The scheduled health check found the following:');

        foreach ($this->issues as $issue) {
            $mail->line("• {$issue}");
        }

        return $mail->line('This alert won\'t repeat for the same issue(s) for a while — check the System Health page for the live picture.');
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
