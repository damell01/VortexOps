<?php

namespace App\Notifications;

use App\Filament\Pages\SystemHealth;
use App\Notifications\Concerns\EmailsWhenEnabled;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use App\Support\MailTable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Monday email: what the Whatnot import brought in last week, what is not
 * healthy, and how long the saved Whatnot sign-in has left.
 *
 * Built from App\Services\ScraperHealthDigest — every line is a count or a
 * date from the database or the cookie file.
 */
class ScraperHealthDigestNotification extends Notification
{
    use EmailsWhenEnabled;

    protected string $event = 'scraper_health_digest';

    /** @param  array<string, mixed>  $digest  from ScraperHealthDigest::build() */
    public function __construct(public readonly array $digest) {}

    public function sessionLine(): string
    {
        $s = $this->digest['session'];
        $date = $s['expires_at']?->format('M j, Y');

        return match ($s['state']) {
            'missing' => 'No saved Whatnot sign-in was found. Run `php artisan whatnot:login` to import cookies — imports cannot sign themselves in.',
            'expired' => "The saved Whatnot sign-in expired on {$date}. Run `php artisan whatnot:login` with fresh cookies.",
            'expiring' => "The saved Whatnot sign-in expires in {$s['days_left']} ".str('day')->plural($s['days_left'])." ({$date}). Refresh it before then with `php artisan whatnot:login`.",
            'unknown' => 'The saved Whatnot sign-in does not record an expiry date'.($s['saved_at'] ? ' (saved '.$s['saved_at']->format('M j, Y').')' : '').'. If imports start failing with exit code 3, refresh it.',
            default => "The saved Whatnot sign-in is good until {$date} ({$s['days_left']} days).",
        };
    }

    public function headline(): string
    {
        return $this->digest['needs_attention']
            ? 'The Whatnot import needs attention.'
            : 'The Whatnot import is healthy.';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->digest;
        $i = $d['imported'];

        $mail = (new MailMessage)
            ->subject(($d['needs_attention'] ? 'Action needed — ' : '')."Whatnot import health, {$d['period']}")
            ->greeting('Whatnot import health')
            ->line("**{$this->headline()}** {$d['period']}.")
            ->line('**Imported**')
            ->line(MailTable::stats([
                'New shows' => (string) $i['new_shows'],
                'Refreshed' => (string) $i['refreshed_shows'],
                'Orders' => (string) $i['orders'],
                'Shipments' => $i['shipments_new'].' new · '.$i['shipments_updated'].' updated',
            ]))
            ->line('**Whatnot sign-in**')
            ->line($this->sessionLine());

        if ($d['pipelines'] !== []) {
            $mail->line('**Pipelines that are not OK**');
            $mail->line(MailTable::make(['Pipeline', 'State', 'Note'], array_map(fn ($p) => [
                $p['label'], ['text' => $p['state'], 'tone' => 'bad'], $p['note'],
            ], $d['pipelines'])));
        }

        if ($d['failures']['count'] > 0) {
            $mail->line("**{$d['failures']['count']} failed or partial ".str('import')->plural($d['failures']['count']).'**');
            $mail->line(MailTable::make(['Source', 'Failed', 'Last error'], array_map(fn ($f) => [
                $f['source'], (string) $f['count'], $f['last_error'] !== '' ? $f['last_error'] : '—',
            ], $d['failures']['by_source']), numeric: [1]));
        }

        $g = $d['gaps'];
        if ($g['shows_without_orders'] > 0 || $g['shows_without_figures'] > 0 || $g['quiet_channels'] !== []) {
            $mail->line('**Gaps**');
            if ($g['shows_without_orders'] > 0) {
                $mail->line("• {$g['shows_without_orders']} ".str('show')->plural($g['shows_without_orders']).' sold items but have no orders imported: '.implode('; ', $g['shows_without_orders_examples']));
            }
            if ($g['shows_without_figures'] > 0) {
                $mail->line("• {$g['shows_without_figures']} ".str('show')->plural($g['shows_without_figures']).' ran but have no revenue figures yet');
            }
            if ($g['quiet_channels'] !== []) {
                $mail->line('• No shows imported for: '.implode(', ', $g['quiet_channels']).' (fine if they did not stream)');
            }
        }

        return $mail->action('Open System Health', SystemHealth::getUrl());
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title('Whatnot import health')
            ->body($this->headline().' '.$this->sessionLine())
            ->icon('heroicon-o-signal')
            ->actions([Action::make('open')->label('System Health')->url(SystemHealth::getUrl())->button()->markAsRead()]);

        return ($this->digest['needs_attention'] ? $notification->warning() : $notification->success())->getDatabaseMessage();
    }
}
