<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Wednesday check-in: revenue and show counts for the current week so far
 * (Monday through today), so ops can catch a slow week early instead of
 * finding out at the Friday review. Sent to whoever NotificationRouter
 * resolves for the 'midweek_report' type (admins by default).
 */
class MidweekReportNotification extends Notification
{
    public function __construct(
        public readonly string $weekLabel,
        public readonly int $showCount,
        public readonly float $grossRevenue,
        public readonly int $unitsSold,
        public readonly string $reportUrl,
        public readonly ?float $pacingPct = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function pacingLine(): ?string
    {
        if ($this->pacingPct === null) {
            return null;
        }

        $direction = $this->pacingPct >= 0 ? 'ahead of' : 'behind';

        return 'Pacing ' . number_format(abs($this->pacingPct), 1) . "% {$direction} the last 4 weeks' average for this point in the week.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gross = number_format($this->grossRevenue, 2);

        $mail = (new MailMessage)
            ->subject("Mid-week report — week of {$this->weekLabel}")
            ->greeting('Mid-week check-in')
            ->line("So far this week ({$this->weekLabel}):")
            ->line("• {$this->showCount} show(s)")
            ->line("• \${$gross} gross revenue")
            ->line("• {$this->unitsSold} units sold");

        if ($pacing = $this->pacingLine()) {
            $mail->line($pacing);
        }

        return $mail->action('View full report', $this->reportUrl);
    }

    public function toDatabase(object $notifiable): array
    {
        $gross = number_format($this->grossRevenue, 2);
        $body  = "{$this->showCount} shows, \${$gross} gross so far this week ({$this->weekLabel}).";
        if ($pacing = $this->pacingLine()) {
            $body .= ' ' . $pacing;
        }

        return FilamentNotification::make()
            ->title('Mid-week report')
            ->body($body)
            ->icon('heroicon-o-chart-bar')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('View report')
                    ->url($this->reportUrl)
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
