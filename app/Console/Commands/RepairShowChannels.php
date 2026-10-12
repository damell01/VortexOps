<?php

namespace App\Console\Commands;

use App\Services\ShowChannelRepair;
use Illuminate\Console\Command;

class RepairShowChannels extends Command
{
    protected $signature = 'whatnot:repair-channels {--apply : Make the changes (without it, only shows what would change)}';

    protected $description = 'Split shows that swallowed another channel\'s stream and move shows to the channel they ran on';

    public function handle(ShowChannelRepair $repair): int
    {
        $plans = $repair->plan();

        if ($plans === []) {
            $this->info('Nothing to repair: no show has evidence of being on the wrong channel or merged.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($plans as ['show' => $show, 'actions' => $actions]) {
            $rows[] = [
                $show->id,
                $show->show_date?->toDateString(),
                mb_strimwidth((string) $show->title, 0, 44, '…'),
                $show->channel?->name ?? '—',
                collect($actions)->map(fn ($a) => match ($a['type']) {
                    'split' => "Split out {$a['channel']} stream as a new show ({$a['orders']} orders move)",
                    'move_to_existing' => "Move {$a['channel']} stream to show #{$a['existing_id']} ({$a['orders']} orders)",
                    'move' => "Move to {$a['channel']}",
                    'restore_figures' => 'Restore this show\'s own figures',
                    'check_figures' => 'Leave figures (approved/paid) — noted for checking',
                    'review' => 'Needs a person: '.$a['reason'],
                    'confirm' => 'Channel confirmed — clear the review flag',
                })->implode("\n"),
            ];
        }
        $this->table(['Show', 'Date', 'Title', 'Channel now', 'Change'], $rows);

        if (! $this->option('apply')) {
            $this->newLine();
            $this->line(count($plans).' show(s) would change. Nothing was changed — run again with <comment>--apply</comment> to make these changes.');

            return self::SUCCESS;
        }

        $totals = $repair->apply($plans);
        $this->newLine();
        $this->info("Done: {$totals['split']} stream(s) split out, {$totals['moved']} show(s) moved, {$totals['confirmed']} confirmed, {$totals['orders_moved']} order(s) moved.");
        if ($totals['review']) {
            $this->warn("{$totals['review']} show(s) still need a person — Dashboard → Needs Attention → shows need a channel confirmed.");
        }

        return self::SUCCESS;
    }
}
