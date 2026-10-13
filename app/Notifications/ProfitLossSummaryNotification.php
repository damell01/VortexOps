<?php

namespace App\Notifications;

use App\Filament\Pages\ProfitLoss;
use App\Notifications\Concerns\EmailsWhenEnabled;
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

    private function showLine(array $s): string
    {
        return "{$s['title']} ({$s['date']}, {$s['streamer']}) — sales {$this->money($s['sales'])}, COGS {$this->money($s['cogs'])}, pay {$this->money($s['payouts'])}, **profit {$this->money($s['profit'])}**"
            .($s['incomplete'] ? ' · incomplete: '.implode(', ', $s['incomplete']) : '');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->summary['totals'];
        $mail = (new MailMessage)
            ->subject($this->title().': '.$this->money($t['profit']).' profit')
            ->greeting($this->title())
            ->line('**'.$this->headline().'**');

        if ($t['shows'] > 0) {
            $mail->line("Earnings {$this->money($t['earnings'])} · COGS {$this->money($t['cogs'])} · Streamer pay {$this->money($t['payouts'])} · Margin {$t['margin_pct']}%");
        }

        if ($this->summary['streamers'] !== []) {
            $mail->line('**By streamer**');
            foreach ($this->summary['streamers'] as $s) {
                $mail->line("• {$s['label']}: {$this->money($s['profit'])} profit on {$this->money($s['sales'])} sales, {$s['shows']} ".str('show')->plural($s['shows']).($s['losing'] ? " ({$s['losing']} losing)" : ''));
            }
        }

        $losing = array_values(array_filter($this->summary['shows'], fn ($s) => $s['profit'] < 0));
        if ($losing !== []) {
            $mail->line('**Shows that lost money**');
            foreach ($losing as $s) {
                $mail->line('• '.$this->showLine($s));
            }
        }

        $rest = array_values(array_filter($this->summary['shows'], fn ($s) => $s['profit'] >= 0));
        if ($rest !== []) {
            $mail->line('**Every other show**');
            foreach ($rest as $s) {
                $mail->line('• '.$this->showLine($s));
            }
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
