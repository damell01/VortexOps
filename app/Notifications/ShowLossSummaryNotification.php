<?php

namespace App\Notifications;

use App\Filament\Pages\ProfitLoss;
use App\Filament\Pages\WhatnotLosses;
use App\Notifications\Concerns\EmailsWhenEnabled;
use App\Services\NotificationRouter;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A summary of shows with negative Whatnot earnings, or of finalized shows
 * that lost money, for a day or a week (ShowLossDigest).
 *
 * Sent on its schedule through the event's rule, or by hand from the report
 * page — then to the people and channels picked there ($channels).
 */
class ShowLossSummaryNotification extends Notification
{
    use EmailsWhenEnabled;

    /** @param  list<string>|null  $channels  'database' / 'mail', when sent by hand */
    public function __construct(protected string $event, public readonly array $digest, private ?array $channels = null) {}

    public function via(object $notifiable): array
    {
        if ($this->channels === null) {
            return app(NotificationRouter::class)->channelsFor($this->event, $notifiable);
        }

        return array_values(array_filter($this->channels, fn ($c) => $c !== 'mail' || NotificationRouter::emailIsEnabled()));
    }

    private function money(float $v): string
    {
        return ($v < 0 ? '−$' : '$').number_format(abs($v), 2);
    }

    private function whatnot(): bool
    {
        return $this->event === 'whatnot_negative';
    }

    public function title(): string
    {
        return ($this->whatnot() ? 'Negative Whatnot earnings' : 'Finalized business losses').' — '.$this->digest['label'];
    }

    public function headline(): string
    {
        $n = $this->digest['count'];
        if ($n === 0) {
            return $this->whatnot() ? 'No shows had negative estimated Whatnot earnings.' : 'No finalized show lost money.';
        }

        return $this->whatnot()
            ? "{$n} ".str('show')->plural($n)." with negative estimated Whatnot earnings, {$this->money($this->digest['total'])} in total."
            : "{$n} finalized ".str('show')->plural($n)." lost {$this->money(abs($this->digest['total']))} after COGS and pay.";
    }

    private function url(): string
    {
        $range = ['from' => $this->digest['from'], 'to' => $this->digest['to']];

        return $this->whatnot()
            ? WhatnotLosses::getUrl($range)
            : ProfitLoss::getUrl(['tab' => 'losses', 'period' => 'week', 'anchor' => $this->digest['from']]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->greeting($this->title())->line('**'.$this->headline().'**');
        foreach ($this->digest['shows'] as $s) {
            $mail->line($this->whatnot()
                ? "• {$s['title']} ({$s['date']}, {$s['streamer']}, {$s['business']}) — **{$this->money($s['amount'])}**"
                : "• {$s['title']} ({$s['date']}, {$s['streamer']}) — earnings {$this->money($s['earnings'])}, COGS {$this->money($s['cogs'])}, pay {$this->money($s['payouts'])}, **{$this->money($s['amount'])}**");
        }
        if ($this->whatnot() && $this->digest['count'] > 0) {
            $mail->line('_Whatnot estimates may change as analytics refresh. Business costs and payroll are not included._');
        }

        return $mail->action($this->whatnot() ? 'Open Whatnot Losses' : 'Open Profit & Loss', $this->url());
    }

    public function toDatabase(object $notifiable): array
    {
        $body = $this->headline();
        $top = array_slice($this->digest['shows'], 0, 5);
        if ($top !== []) {
            $body .= "\n".implode('; ', array_map(fn ($s) => "{$s['title']} ({$this->money($s['amount'])})", $top))
                .($this->digest['count'] > 5 ? ' and '.($this->digest['count'] - 5).' more' : '');
        }

        return FilamentNotification::make()->title($this->title())->body($body)
            ->icon('heroicon-o-arrow-trending-down')->warning()
            ->actions([Action::make('open')->label('See the shows')->url($this->url())->button()->markAsRead()])
            ->getDatabaseMessage();
    }
}
