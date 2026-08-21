<?php

namespace App\Console\Commands;

use App\Models\Show;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditWhatnotShows extends Command
{
    protected $signature = 'whatnot:audit-shows {--days=365 : Date window for suspicious title/date checks}';

    protected $description = 'Audit imported Whatnot shows for duplicate UUIDs and suspicious repeated title/date records';

    public function handle(): int
    {
        $duplicateIds = Show::query()
            ->whereNotNull('whatnot_show_id')
            ->selectRaw('whatnot_show_id, COUNT(*) as row_count')
            ->groupBy('whatnot_show_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('row_count')
            ->get();

        $this->newLine();
        $this->info('Duplicate Whatnot UUIDs');
        if ($duplicateIds->isEmpty()) {
            $this->line('None. The current UUID-based sync is not inserting the same Whatnot show twice.');
        } else {
            $rows = [];
            foreach ($duplicateIds as $group) {
                foreach (Show::where('whatnot_show_id', $group->whatnot_show_id)->orderBy('id')->get() as $show) {
                    $rows[] = [
                        $show->id,
                        $show->whatnot_show_id,
                        $show->show_date?->format('Y-m-d') ?? '—',
                        $show->title,
                        $show->shipments()->count(),
                        $show->orders()->count(),
                    ];
                }
            }
            $this->table(['DB ID', 'Whatnot UUID', 'Date', 'Title', 'Shipments', 'Orders'], $rows);
        }

        $days = max(1, (int) $this->option('days'));
        $windowStart = today()->subDays($days);

        // Same title + same date is suspicious, but not automatically wrong. We
        // intentionally report it rather than deleting it because Whatnot can
        // have distinct UUIDs with intentionally reused titles.
        $sameTitleDate = Show::query()
            ->where('import_source', 'auto_whatnot')
            ->whereDate('show_date', '>=', $windowStart)
            ->whereNotNull('title')
            ->selectRaw('title, show_date, COUNT(*) as row_count')
            ->groupBy('title', 'show_date')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('show_date')
            ->get();

        $this->newLine();
        $this->info('Suspicious same-title / same-date groups');
        if ($sameTitleDate->isEmpty()) {
            $this->line('None in the selected window.');
        } else {
            $rows = [];
            foreach ($sameTitleDate as $group) {
                $matches = Show::query()
                    ->where('title', $group->title)
                    ->whereDate('show_date', $group->show_date)
                    ->orderBy('id')
                    ->get();
                foreach ($matches as $show) {
                    $rows[] = [
                        $show->id,
                        $show->show_date?->format('Y-m-d') ?? '—',
                        $show->title,
                        $show->whatnot_show_id ?? 'NO UUID',
                        data_get($show->raw_import_payload, '_show_state', '—'),
                    ];
                }
            }
            $this->table(['DB ID', 'Date', 'Title', 'Whatnot UUID', 'State'], $rows);
        }

        $this->newLine();
        $this->comment('Only rows sharing the same Whatnot UUID are definitive duplicates. Reused titles alone are not enough to delete a show.');

        return self::SUCCESS;
    }
}
