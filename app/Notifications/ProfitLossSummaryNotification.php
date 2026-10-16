<?php

namespace App\Notifications;

use App\Filament\Pages\ProfitLoss;
use App\Notifications\Concerns\EmailsWhenEnabled;
use App\Support\MailTable;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The daily or weekly Profit & Loss summary: the totals, how each streamer
 * did, and every show — losing ones first. Every figure comes from
 * ProfitLossReport, the same numbers as the Profit & Loss page.
 */
class ProfitLossSummaryNotification extends Notification
{
    use EmailsWhenEnabled;

    /**
     * @param  'daily_pl_summary'|'weekly_pl_summary'  $event
     * @param  array{label:string,period:string,anchor:string,totals:array,streamers:array,shows:array}  $summary
     */
    public function __construct(protected string $event, public readonly array $summary) {}

    private function money(float $v): string
    {
        return ($v < 0 ? '−$' : '$').number_format(abs($v), 2);
    }

    public function title(): string
    {
        return ($this->event === 'daily_pl_summary' ? 'Daily' : 'Weekly').' P&L — '.$this->summary['label'];
    }

    public function headline(): string
    {
        $t = $this->summary['totals'];
        if ($t['shows'] === 0) {
            return 'No shows aired.';
        }

        return "{$this->money($t['profit'])} profit after pay on {$this->money($t['sales'])} sales from {$t['shows']} ".str('show')->plural($t['shows'])
            .($t['losing'] ? " · {$t['losing']} lost money" : '')
            .($t['incomplete'] ? " · {$t['incomplete']} incomplete" : '').'.';
    }

    private function url(): string
    {
        return ProfitLoss::getUrl(['period' => $this->summary['period'], 'anchor' => $this->summary['anchor']]);
    }

    /** One show as a table row: its title with date and streamer under it, then the figures. */
    private function showRow(array $s): array
    {
        $sub = "{$s['date']} · {$s['streamer']}".($s['incomplete'] ? ' · incomplete: '.implode(', ', $s['incomplete']) : '');

        return [
            ['text' => $s['title'], 'sub' => $sub],
            $this->money($s['sales']),
            $this->money($s['cogs']),
            $this->money($s['payouts']),
            ['text' => $this->money($s['profit']), 'tone' => $s['profit'] < 0 ? 'bad' : null],
        ];
    }

    private function showTable(array $shows): \Illuminate\Support\HtmlString
    {
        return MailTable::make(['Show', 'Sales', 'COGS', 'Pay', 'Profit'], array_map(fn ($s) => $this->showRow($s), $shows), numeric: [1, 2, 3, 4]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->summary['totals'];
        $mail = (new MailMessage)
            ->subject($this->title().': '.$this->money($t['profit']).' profit')
            ->greeting($this->title())
            ->line('**'.$this->headline().'**');

        if ($t['shows'] > 0) {
            $mail->line(MailTable::stats([
                'Earnings' => $this->money($t['earnings']),
                'COGS' => $this->money($t['cogs']),
                'Streamer pay' => $this->money($t['payouts']),
                'Margin' => ['text' => $t['margin_pct'].'%', 'tone' => $t['margin_pct'] < 0 ? 'bad' : null],
            ]));
        }

        if ($this->summary['streamers'] !== []) {
            $mail->line('**By streamer**');
            $mail->line(MailTable::make(['Streamer', 'Shows', 'Sales', 'Profit'], array_map(fn ($s) => [
                ['text' => $s['label'], 'sub' => $s['losing'] ? "{$s['losing']} losing" : null],
                (string) $s['shows'],
                $this->money($s['sales']),
                ['text' => $this->money($s['profit']), 'tone' => $s['profit'] < 0 ? 'bad' : null],
            ], $this->summary['streamers']), numeric: [1, 2, 3]));
        }

        $losing = array_values(array_filter($this->summary['shows'], fn ($s) => $s['profit'] < 0));
        if ($losing !== []) {
            $mail->line('**Shows that lost money**');
            $mail->line($this->showTable($losing));
        }

        $rest = array_values(array_filter($this->summary['shows'], fn ($s) => $s['profit'] >= 0));
        if ($rest !== []) {
            $mail->line('**Every other show**');
            $mail->line($this->showTable($rest));
        }

        if ($t['incomplete'] > 0) {
            $mail->line('_Incomplete shows are missing earnings, COGS or final pay — their profit may change._');
        }

        return $mail->action('Open Profit & Loss', $this->url());
    }

    public function toDatabase(object $notifiable): array
    {
        $t = $this->summary['totals'];
        $losing = array_values(array_filter($this->summary['shows'], fn ($s) => $s['profit'] < 0));
        $body = $this->headline();
        if ($losing !== []) {
            $body .= "\nLost money: ".implode('; ', array_map(fn ($s) => "{$s['title']} ({$this->money($s['profit'])})", array_slice($losing, 0, 5)))
                .(count($losing) > 5 ? ' and '.(count($losing) - 5).' more' : '');
        }

        $n = FilamentNotification::make()->title($this->title())->body($body)
            ->icon('heroicon-o-presentation-chart-line')
            ->actions([Action::make('open')->label('See every show')->url($this->url())->button()->markAsRead()]);

        return ($t['profit'] < 0 ? $n->warning() : $n->info())->getDatabaseMessage();
    }
}
