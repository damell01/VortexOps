<?php

namespace App\Console\Commands;

use App\Filament\Resources\WhatnotLedgerResource;
use App\Notifications\VortexAlert;
use App\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tell admins when ledger money is sitting unassigned: no approved show,
 * streamer or house allocation. Entries get two days for the hourly matcher
 * and order imports to catch up; payouts to the bank need no show.
 */
class LedgerUnassignedAlert extends Command
{
    protected $signature = 'ledger:unassigned-alert {--days=30 : How far back to look} {--grace=2 : Days to wait before an entry counts}';

    protected $description = 'Notify admins about ledger entries not tied to a show, streamer or house';

    public function handle(): int
    {
        $row = self::unassigned((int) $this->option('days'), (int) $this->option('grace'));
        $this->info('Unassigned ledger entries: ' . $row['entries'] . ' · $' . number_format($row['net'], 2));
        if ($row['entries'] === 0) return self::SUCCESS;

        Notifier::send('ledger_unassigned', new VortexAlert(
            event: 'ledger_unassigned',
            title: number_format($row['entries']) . ' ledger ' . ($row['entries'] === 1 ? 'entry isn\'t' : 'entries aren\'t') . ' tied to a show',
            body: 'Net $' . number_format($row['net'], 2) . ' from the last ' . (int) $this->option('days') . ' days has no approved show, streamer or house assignment. Assign or approve them so pay and reports are complete.',
            tone: 'warning',
            links: ['Review unassigned' => WhatnotLedgerResource::getUrl('index', ['review' => 'needs_attribution'])],
            icon: 'heroicon-o-exclamation-triangle',
        ));

        return self::SUCCESS;
    }

    /** @return array{entries:int, net:float} */
    public static function unassigned(int $days = 30, int $grace = 2): array
    {
        $row = DB::table('whatnot_ledger_entries as le')
            ->whereBetween('le.created_date', [now()->subDays(max(1, $days))->startOfDay(), now()->subDays(max(0, $grace))])
            ->whereNotNull('le.amount')
            ->whereRaw("LOWER(COALESCE(le.transaction_type, '')) NOT LIKE ? AND LOWER(COALESCE(le.transaction_type, '')) NOT LIKE ? AND LOWER(COALESCE(le.transaction_type, '')) NOT LIKE ?", ['%payout%', '%transfer%', '%withdrawal%'])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('ledger_attributions as a')
                ->whereColumn('a.whatnot_ledger_entry_id', 'le.id')->where('a.status', 'approved'))
            ->selectRaw('COUNT(*) as entries, COALESCE(SUM(le.amount), 0) as net')->first();

        return ['entries' => (int) $row->entries, 'net' => round((float) $row->net, 2)];
    }
}
