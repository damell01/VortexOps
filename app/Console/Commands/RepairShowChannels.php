<?php

namespace App\Console\Commands;

use App\Services\ShowChannelRepair;
use Illuminate\Console\Command;

class RepairShowChannels extends Command
{
    protected $signature = 'whatnot:repair-channels
        {--apply : Make the changes (without it, only shows what would change)}
        {--moves-only : Only move shows to the right channel and confirm channels; leave splits and copies for later}
        {--show=* : Only these show ids}';

    protected $description = 'Split shows that swallowed another channel\'s stream and move shows to the channel they ran on';

    public function handle(ShowChannelRepair $repair): int
    {
        $only = array_map('intval', (array) $this->option('show'));
        $movesOnly = (bool) $this->option('moves-only');

        // 1. Second copies the nightly discovery made of hourly-imported shows.
        $duplicates = $movesOnly ? [] : collect($repair->duplicatePlan())
            ->filter(fn ($d) => ! $only || in_array($d['show']->id, $only, true))->values()->all();
        if ($duplicates !== []) {
            $this->info('Second copies to fold back into the original show:');
            $this->table(['Show', 'Date now', 'Title', 'Copy', 'Correct date', 'Change'], collect($duplicates)->map(fn ($d) => [
                $d['show']->id, $d['show']->show_date?->toDateString(), mb_strimwidth((string) $d['show']->title, 0, 40, '…'),
                '#'.$d['copy']->id, $d['copy']->show_date?->toDateString(),
                $d['blocked'] ? 'Needs a person: '.$d['blocked'] : 'Fold copy in, take its date/time',
            ])->all());
            if ($this->option('apply')) {
                $t = $repair->applyDuplicates($duplicates);
                $this->info("Folded {$t['merged']} copy(ies) back in.".($t['review'] ? " {$t['review']} left for a person." : ''));
            }
            $this->newLine();
        }

        // 2. Merged streams and shows on the wrong channel.
        $plans = collect($repair->plan())
            ->filter(fn ($p) => ! $only || in_array($p['show']->id, $only, true))
            // --moves-only: shows whose whole plan is a channel move or a confirmation.
            ->filter(fn ($p) => ! $movesOnly || collect($p['actions'])->every(fn ($a) => in_array($a['type'], ['move', 'confirm'], true)))
            ->values()->all();

        if ($plans === []) {
            $this->info('Nothing to repair: no show has evidence of being on the wrong channel or merged.');
            if ($duplicates !== [] && ! $this->option('apply')) {
                $this->line('Nothing was changed — run again with <comment>--apply</comment> to fold in the copies above.');
            }

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
            $this->line(count($plans).' show(s) would change'.($duplicates ? ' (after folding in the copies above)' : '').'. Nothing was changed — run again with <comment>--apply</comment> to make these changes.');

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
