<?php

namespace App\Notifications;

use App\Filament\Pages\ShowFormatComparison;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Monday email to the owner: how last week went and what is waiting.
 *
 * Built from App\Services\WeeklyOwnerSummary with a fixed template — every
 * figure is a real number from the database, nothing is generated.
 */
class WeeklyOwnerSummaryNotification extends Notification
{
    use EmailsWhenEnabled;

    protected string $event = 'weekly_owner_summary';

    /** @param  array<string, mixed>  $summary  from WeeklyOwnerSummary::build() */
    public function __construct(public readonly array $summary) {}

    private function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }

    private function change(?float $pct): string
    {
        return $pct === null ? 'no shows the week before' : (($pct >= 0 ? '+' : '').number_format($pct, 1).'% vs the week before');
    }

    public function headline(): string
    {
        $t = $this->summary['totals'];

        return $t['shows'] === 0
            ? 'No shows ran last week.'
            : $this->money($t['gross'])." gross, {$this->money($t['net'])} net from {$t['shows']} ".str('show')->plural($t['shows'])." ({$this->change($this->summary['change']['gross_pct'])}).";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->summary;
        $t = $s['totals'];

        $mail = (new MailMessage)
            ->subject("Weekly summary — {$s['week_label']}: {$this->money($t['gross'])} gross")
            ->greeting("Week of {$s['week_label']}")
            ->line('**'.$this->headline().'**');

        if ($t['shows'] > 0) {
            $perHour = $t['hours'] > 0 ? ' · '.$this->money($t['gross'] / $t['hours']).'/hr gross' : '';
            $mail->line("{$t['hours']} hours worked · {$t['units']} units sold{$perHour}");
        }

        if ($s['channels'] !== []) {
            $mail->line('**By channel**');
            foreach ($s['channels'] as $c) {
                $mail->line("• {$c['name']}: {$this->money($c['gross'])} gross, {$this->money($c['net'])} net ({$c['shows']} ".str('show')->plural($c['shows']).')');
            }
        }

        if ($s['streamers'] !== []) {
            $mail->line('**Top streamers** (a shared show counts for each streamer on it)');
            foreach ($s['streamers'] as $st) {
                $mail->line("• {$st['name']}: {$this->money($st['gross'])} over {$st['shows']} ".str('show')->plural($st['shows']));
            }
        }

        if ($s['tags'] !== []) {
            $mail->line('**What earned most per hour** (from show titles)');
            foreach ($s['tags'] as $tag) {
                $vs = $tag['gross_vs_overall_pct'] === null ? '' : ' ('.(($tag['gross_vs_overall_pct'] >= 0 ? '+' : '').number_format($tag['gross_vs_overall_pct'], 1)).'% vs all shows)';
                $mail->line("• {$tag['label']}: {$this->money($tag['gross_per_hour'])}/hr gross over {$tag['shows']} shows{$vs}");
            }
        }

        $mail->line('**Needs your attention**');
        if ($s['attention'] === []) {
            $mail->line('Nothing — you are all caught up.');
        } else {
            foreach ($s['attention'] as $item) {
                $mail->line("• {$item['count']} {$item['label']}");
            }
        }

        return $mail
            ->action('Open the dashboard', url('/admin'))
            ->line('Format details: '.ShowFormatComparison::getUrl());
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Weekly summary — '.$this->summary['week_label'])
            ->body($this->headline())
            ->icon('heroicon-o-chart-bar-square')
            ->info()
            ->actions([
                Action::make('open')->label('Open dashboard')->url(url('/admin'))->button()->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
