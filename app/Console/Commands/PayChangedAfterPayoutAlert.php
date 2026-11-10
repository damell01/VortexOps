<?php

namespace App\Console\Commands;

use App\Filament\Pages\PayChangedSincePaid;
use App\Notifications\VortexAlert;
use App\Services\Notifier;
use App\Services\PayChangedAfterPayout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Hourly: work out which shows changed after their pay was locked, keep the
 * count for the Payroll badge and card, and tell admins when new ones appear.
 */
class PayChangedAfterPayoutAlert extends Command
{
    protected $signature = 'payroll:changed-after-payout';

    protected $description = 'Find shows whose earnings changed after their pay was finalized or paid';

    public function handle(PayChangedAfterPayout $service): int
    {
        $rows = $service->refresh();
        $keys = $rows->map(fn ($r) => $r['show_id'] . ':' . $r['streamer_id'] . ':' . $r['now'])->sort()->values()->all();
        $this->info('Changed after payout: ' . count($keys));

        // Only tell people about lines they have not been told about yet.
        $known = Cache::get('payroll:pay-changed-after-payout:notified', []);
        $new = array_values(array_diff($keys, is_array($known) ? $known : []));
        Cache::forever('payroll:pay-changed-after-payout:notified', $keys);
        if ($new === []) return self::SUCCESS;

        $owed = $rows->filter(fn ($r) => in_array($r['show_id'] . ':' . $r['streamer_id'] . ':' . $r['now'], $new, true));
        Notifier::send('pay_changed_after_payout', new VortexAlert(
            event: 'pay_changed_after_payout',
            title: count($new) . ' paid ' . (count($new) === 1 ? 'show changed' : 'shows changed') . ' after payout',
            body: 'Earnings changed after the pay was locked: ' . $owed->take(3)->map(fn ($r) => $r['show'] . ' (' . $r['streamer'] . ' ' . ($r['difference'] >= 0 ? '+' : '−') . '$' . number_format(abs($r['difference']), 2) . ')')->implode(', ')
                . ($owed->count() > 3 ? ' and ' . ($owed->count() - 3) . ' more' : '') . '. Settle the difference on the next run.',
            tone: 'warning',
            links: ['Review' => PayChangedSincePaid::getUrl()],
            icon: 'heroicon-o-exclamation-triangle',
        ));

        return self::SUCCESS;
    }
}
