<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the database small without losing anything the app uses.
 *
 * Kept forever: shows, orders, ledger entries (every shipping adjustment,
 * refund and correction), their show/streamer matches and every decision a
 * person made, pay runs and payouts, and each real change to a show.
 *
 * Trimmed after a while:
 *  - saved Whatnot API responses: the newest 20 of each feed and the last
 *    day are kept (they are applied as they arrive; the API can be asked again);
 *  - the raw copy kept beside ledger entries and orders after 30 days: only
 *    the keys the app still reads (IDs, livestream evidence, labels) stay;
 *  - automatic ledger-match history after 30 days (people's decisions stay);
 *  - "changes" that did not change anything (same old and new value);
 *  - import logs and sync audits after 30 days, failed jobs after 30 days,
 *    the HTML copy of sent emails after 30 days, read notifications after
 *    60 days, the activity log after 90 days.
 *
 * Dry run by default: it says what it would remove. --apply removes it, in
 * small batches, and --optimize then hands the space back to the disk.
 */
class StorageTrim extends Command
{
    protected $signature = 'storage:trim {--apply : Remove it (otherwise only report)} {--optimize : Rebuild trimmed tables afterwards so MySQL returns the space}';

    protected $description = 'Trim saved API responses, old raw copies, logs and no-op history (dry run unless --apply)';

    /** Raw keys the app still reads; everything else in an old raw copy goes. */
    private const LEDGER_KEEP = ['ledger_transaction_id', 'livestream_id', 'live_id', 'whatnot_live_id', 'source_live_id', 'order_id', 'order_hash', 'shipment_id', 'listing_id'];

    private const ORDER_KEEP = ['livestream_id', 'live_id', 'whatnot_live_id', 'source_live_id', 'order_id', 'shipment_id', 'lot_number', 'documents', 'status', 'detail_url', 'open_url'];

    private bool $apply = false;

    /** @var array<string, bool> */
    private array $touched = [];

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply');
        $this->info($this->apply ? 'Trimming…' : 'Dry run: nothing is removed. Add --apply to trim.');

        $rows = [];
        $rows[] = $this->snapshots();
        $rows[] = $this->slimRaw('whatnot_ledger_entries', 'raw_data', self::LEDGER_KEEP, 30);
        $rows[] = $this->slimRaw('whatnot_show_orders', 'raw_data', self::ORDER_KEEP, 30);
        $rows[] = $this->deleteWhere('ledger_attribution_events', 'automatic match history older than 30 days',
            fn ($q) => $q->whereNull('actor_id')->where('created_at', '<', now()->subDays(30)));
        $rows[] = $this->deleteWhere('show_change_logs', 'changes with the same old and new value',
            fn ($q) => $q->whereColumn('old_value', 'new_value'));
        $rows[] = $this->deleteWhere('show_ingestion_logs', 'import log older than 30 days', fn ($q) => $q->where('created_at', '<', now()->subDays(30)));
        $rows[] = $this->deleteWhere('whatnot_api_sync_audits', 'sync audits older than 30 days', fn ($q) => $q->where('created_at', '<', now()->subDays(30)));
        $rows[] = $this->deleteWhere('failed_jobs', 'failed jobs older than 30 days', fn ($q) => $q->where('failed_at', '<', now()->subDays(30)));
        $rows[] = $this->deleteWhere('notifications', 'read notifications older than 60 days', fn ($q) => $q->whereNotNull('read_at')->where('read_at', '<', now()->subDays(60)));
        $rows[] = $this->deleteWhere('activity_log', 'activity log older than 90 days', fn ($q) => $q->where('created_at', '<', now()->subDays(90)));
        $rows[] = $this->clearColumn('email_logs', 'html', 'email copies older than 30 days', fn ($q) => $q->where('created_at', '<', now()->subDays(30)));

        $this->table(['What', 'Table', $this->apply ? 'Removed / slimmed' : 'Would remove / slim'], array_values(array_filter($rows)));

        if ($this->apply && $this->option('optimize')) {
            $this->optimize();
        }

        return self::SUCCESS;
    }

    /**
     * Saved API responses are read right after they are captured (to apply
     * them) and the newest few are shown on the API status page. Keep the
     * newest 20 of each channel's feed plus anything from the last day.
     */
    private function snapshots(): ?array
    {
        if (! Schema::hasTable('whatnot_api_snapshots')) return null;
        $groups = DB::table('whatnot_api_snapshots')->select('whatnot_channel_id', 'resource')->distinct()->get();
        $count = 0;
        foreach ($groups as $group) {
            $keepFrom = DB::table('whatnot_api_snapshots')->where('whatnot_channel_id', $group->whatnot_channel_id)->where('resource', $group->resource)
                ->orderByDesc('id')->skip(19)->value('id');
            if ($keepFrom === null) continue;
            $query = fn () => DB::table('whatnot_api_snapshots')->where('whatnot_channel_id', $group->whatnot_channel_id)->where('resource', $group->resource)
                ->where('id', '<', $keepFrom)->where('fetched_at', '<', now()->subDay());
            $count += $query()->count();
            if ($this->apply) {
                do {
                    $ids = $query()->orderBy('id')->limit(500)->pluck('id');
                    DB::table('whatnot_api_snapshots')->whereIn('id', $ids)->delete();
                } while ($ids->isNotEmpty());
            }
        }
        if ($this->apply && $count) $this->touched['whatnot_api_snapshots'] = true;

        return ['Saved Whatnot API responses older than a day (newest 20 of each feed kept)', 'whatnot_api_snapshots', number_format($count)];
    }

    /** Old raw copies: keep only the keys the app reads, plus every "_"-prefixed evidence key. */
    private function slimRaw(string $table, string $column, array $keep, int $days): ?array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) return null;
        $slimmed = 0;
        $saved = 0;
        DB::table($table)->select(['id', $column])->whereNotNull($column)->where('created_at', '<', now()->subDays($days))
            ->orderBy('id')->chunkById(1000, function ($rows) use ($table, $column, $keep, &$slimmed, &$saved) {
                foreach ($rows as $row) {
                    $raw = json_decode((string) $row->{$column}, true);
                    if (! is_array($raw)) continue;
                    $slim = array_filter($raw, fn ($value, $key) => in_array($key, $keep, true) || str_starts_with((string) $key, '_'), ARRAY_FILTER_USE_BOTH);
                    if (count($slim) === count($raw)) continue;
                    $encoded = $slim === [] ? null : json_encode($slim, JSON_UNESCAPED_SLASHES);
                    $saved += strlen((string) $row->{$column}) - strlen((string) $encoded);
                    $slimmed++;
                    if ($this->apply) DB::table($table)->where('id', $row->id)->update([$column => $encoded]);
                }
            });
        if ($this->apply && $slimmed) $this->touched[$table] = true;

        return ["Raw copies older than {$days} days (IDs and evidence kept), about " . $this->size($saved), $table, number_format($slimmed)];
    }

    private function deleteWhere(string $table, string $label, \Closure $where): ?array
    {
        if (! Schema::hasTable($table)) return null;
        $count = $where(DB::table($table))->count();
        if ($this->apply && $count) {
            do {
                $ids = $where(DB::table($table))->orderBy('id')->limit(1000)->pluck('id');
                DB::table($table)->whereIn('id', $ids)->delete();
            } while ($ids->isNotEmpty());
            $this->touched[$table] = true;
        }

        return [ucfirst($label), $table, number_format($count)];
    }

    private function clearColumn(string $table, string $column, string $label, \Closure $where): ?array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) return null;
        $count = $where(DB::table($table))->whereNotNull($column)->count();
        if ($this->apply && $count) {
            $where(DB::table($table))->whereNotNull($column)->update([$column => null]);
            $this->touched[$table] = true;
        }

        return [ucfirst($label) . ' (the record stays)', $table, number_format($count)];
    }

    /** InnoDB keeps freed pages until a table is rebuilt; this hands them back to the disk. */
    private function optimize(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->line('Rebuild skipped: not MySQL.');

            return;
        }
        foreach (array_keys($this->touched) as $table) {
            $this->line("Rebuilding {$table}…");
            DB::statement('OPTIMIZE TABLE `' . str_replace('`', '', $table) . '`');
        }
    }

    private function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = max(0, $bytes);
        while ($value >= 1024 && $i < 3) { $value /= 1024; $i++; }

        return round($value, 1) . ' ' . $units[$i];
    }
}
