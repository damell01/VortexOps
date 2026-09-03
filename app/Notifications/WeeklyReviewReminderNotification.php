<?php

namespace App\Notifications;

use App\Notifications\Concerns\EmailsWhenEnabled;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Friday reminder: N shows from this week are still sitting in Pending
 * Review / Pending Approval. Sent to whoever NotificationRouter resolves
 * for the 'weekly_review_reminder' type (admins by default).
 */
class WeeklyReviewReminderNotification extends Notification
{
    use EmailsWhenEnabled;

    public function __construct(
        public readonly int $pendingCount,
        public readonly string $weekLabel,
        public readonly string $reviewUrl,
        public readonly ?string $narrative = null,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("{$this->pendingCount} show(s) need review — week of {$this->weekLabel}")
            ->greeting('Friday review reminder');

        if ($this->narrative) {
            $mail->line($this->narrative)->line('');
        }

        return $mail
            ->line("{$this->pendingCount} show(s) from the week of {$this->weekLabel} are still in Pending Review or Pending Approval.")
            ->action('Review shows', $this->reviewUrl)
            ->line('Reviewing before the weekend keeps payouts on schedule.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Weekly review reminder')
            ->body($this->narrative ?? "{$this->pendingCount} show(s) from the week of {$this->weekLabel} still need review.")
            ->icon('heroicon-o-calendar-days')
            ->warning()
            ->actions([
                Action::make('open')
                    ->label('Review shows')
                    ->url($this->reviewUrl)
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
