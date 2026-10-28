<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLedgerTimeWindows extends Command
{
    protected $signature = 'ledger:audit-time-windows
        {--limit=1000 : Maximum ledger entries to inspect (max 10000)}
        {--examples=20 : Maximum examples to display}
        {--timezone=America/Chicago : Local timezone used for show start times}
        {--hours=12 : Maximum hours since a show started (1 to 24)}';

    protected $description = 'Read-only ledger to show candidate audit using UTC ledger timestamps and local show start times';

    public function handle(): int
    {
        $limit = min(10000, max(1, (int) $this->option('limit')));
        $examples = min(100, max(0, (int) $this->option('examples')));
        $hours = min(24, max(1, (int) $this->option('hours')));
        $timezone = (string) $this->option('timezone');
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable $e) {
            $this->error('Invalid timezone: '.$timezone);
            return self::FAILURE;
        }

        $stats = ['one_plausible' => 0, 'multiple_plausible' => 0, 'none_plausible' => 0, 'missing_data' => 0];
        $samples = [];
        $query = DB::table('whatnot_ledger_entries')
            ->whereNotNull('whatnot_order_id')
            ->where('whatnot_order_id', '<>', '')
            ->where('whatnot_order_id', '<>', '-')
            ->orderBy('id')->limit($limit)
            ->get(['id', 'whatnot_order_id', 'whatnot_channel_id', 'created_date']);

        // Cache per channel and three-day date span to avoid repeated show queries
        // for consecutive orders from the same channel and date.
        $cache = [];
        foreach ($query as $entry) {
            $candidates = [];
            $localLabel = '';
            if (!$entry->whatnot_channel_id || !$entry->created_date) {
                $category = 'missing_data';
            } else {
                // This conversion is an assumption to verify against Whatnot.
                $local = CarbonImmutable::parse($entry->created_date, 'UTC')->setTimezone($timezone);
                $localLabel = $local->format('Y-m-d H:i:s');
                $date = $local->toDateString();
                $key = $entry->whatnot_channel_id.'|'.$date;
                if (!isset($cache[$key])) {
                    $cache[$key] = DB::table('shows')
                        ->where('whatnot_channel_id', $entry->whatnot_channel_id)
                        ->whereBetween('show_date', [$local->subDay()->toDateString(), $local->toDateString()])
                        ->whereNotNull('start_time')
                        ->get(['id', 'show_date', 'start_time', 'end_time']);
                }
                foreach ($cache[$key] as $show) {
                    $start = CarbonImmutable::parse(substr((string) $show->show_date, 0, 10).' '.(string) $show->start_time, $timezone);
                    $minutes = $start->diffInMinutes($local, false);
                    if ($minutes >= 0 && $minutes <= $hours * 60) {
                        $candidates[] = ['id' => $show->id, 'minutes' => (int) round($minutes)];
                    }
                }
                usort($candidates, fn ($a, $b) => $a['minutes'] <=> $b['minutes']);
                $category = count($candidates) === 1 ? 'one_plausible' : (count($candidates) ? 'multiple_plausible' : 'none_plausible');
            }
            $stats[$category]++;
            if (count($samples) < $examples) {
                $samples[] = [
                    $entry->id, $entry->whatnot_order_id, $localLabel,
                    $category, implode(', ', array_map(fn ($c) => '#'.$c['id'].' ('.$c['minutes'].'m)', array_slice($candidates, 0, 5))),
                ];
            }
        }

        $this->table(['Classification', 'Ledger entries'], collect($stats)->map(fn ($count, $label) => [$label, $count])->values()->all());
        $this->table(['Ledger ID', 'Order ID', 'Local transaction time', 'Classification', 'Nearest shows'], $samples);
        $this->warn("READ ONLY: no changes made. Assumes ledger timestamps are UTC and show starts are {$timezone}. A single plausible show is NOT a verified match. Missing end times and cross-midnight shows require review.");
        return self::SUCCESS;
    }
}
