<?php

namespace App\Notifications;

use App\Filament\Pages\ShowFormatComparison;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use App\Support\MailTable;
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
        return ($value < 0 ? '−$' : '$').number_format(abs($value), 2);
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
            $mail->line(MailTable::stats(array_filter([
                'Gross' => $this->money($t['gross']),
                'Net' => $this->money($t['net']),
                'Hours' => (string) $t['hours'],
                'Units' => (string) $t['units'],
                'Gross / hr' => $t['hours'] > 0 ? $this->money($t['gross'] / $t['hours']) : null,
            ])));
        }

        if ($s['channels'] !== []) {
            $mail->line('**By channel**');
            $mail->line(MailTable::make(['Channel', 'Shows', 'Gross', 'Net'], array_map(fn ($c) => [
                $c['name'], (string) $c['shows'], $this->money($c['gross']), ['text' => $this->money($c['net']), 'tone' => $c['net'] < 0 ? 'bad' : null],
            ], $s['channels']), numeric: [1, 2, 3]));
        }

        if ($s['streamers'] !== []) {
            $mail->line('**Top streamers** (a shared show counts for each streamer on it)');
            $mail->line(MailTable::make(['Streamer', 'Shows', 'Gross'], array_map(fn ($st) => [
                $st['name'], (string) $st['shows'], $this->money($st['gross']),
            ], $s['streamers']), numeric: [1, 2]));
        }

        if ($s['tags'] !== []) {
            $mail->line('**What earned most per hour** (from show titles)');
            $mail->line(MailTable::make(['Format', 'Shows', 'Gross / hr', 'Vs all shows'], array_map(function ($tag) {
                $pct = $tag['gross_vs_overall_pct'];

                return [
                    $tag['label'], (string) $tag['shows'], $this->money($tag['gross_per_hour']),
                    $pct === null ? '—' : ['text' => ($pct >= 0 ? '+' : '').number_format($pct, 1).'%', 'tone' => $pct < 0 ? 'bad' : 'good'],
                ];
            }, $s['tags']), numeric: [1, 2, 3]));
        }

        $mail->line('**Needs your attention**');
        if ($s['attention'] === []) {
            $mail->line('Nothing — you are all caught up.');
        } else {
            $mail->line(MailTable::make(['Item', 'Count'], array_map(fn ($item) => [$item['label'], (string) $item['count']], $s['attention']), numeric: [1]));
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
