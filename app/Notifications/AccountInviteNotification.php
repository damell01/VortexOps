<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "You have a VortexOps account — set your password." Email only, sent straight away. */
class AccountInviteNotification extends Notification
{
    use Queueable;

    public function __construct(public string $url, public ?string $invitedBy = null, public int $days = 7) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) config('app.name', 'VortexOps');

        return (new MailMessage)
            ->subject("You're invited to {$app}")
            ->greeting('Hi ' . trim(explode(' ', (string) $notifiable->name)[0]) . ',')
            ->line(($this->invitedBy ? $this->invitedBy . ' created' : 'An account has been created') . " a {$app} account for you" . ($this->invitedBy ? '.' : ' with this email address.'))
            ->line('Set your password to sign in. Your email address is your username: ' . $notifiable->email)
            ->action('Set your password', $this->url)
            ->line("This link works once and expires in {$this->days} days. If it expires, ask for a new invite.");
    }
}
