<?php

namespace App\Console\Commands;

use App\Support\ShowTimeText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Shows imported from Seller Hub stored the row's text but never the time in
 * it, so most read "Time TBD" and the Best Times heatmap couldn't place them.
 * Reads the time back out of what each show was imported with. Only shows
 * without a time are touched.
 */
class FillShowStartTimes extends Command
{
    protected $signature = 'shows:fill-start-times {--dry-run : Count what would be filled without saving}';

    protected $description = 'Fill missing show start times from their imported Seller Hub text';

    public function handle(): int
    {
        $filled = $noTime = 0;
        $dry = (bool) $this->option('dry-run');

        DB::table('shows')->whereNull('start_time')->whereNotNull('raw_import_payload')
            ->select(['id', 'title', 'show_date', 'raw_import_payload'])
            ->orderBy('id')->chunkById(500, function ($rows) use (&$filled, &$noTime, $dry) {
                foreach ($rows as $row) {
                    $raw = json_decode((string) $row->raw_import_payload, true);
                    $start = is_array($raw) ? ShowTimeText::fromPayload($raw, $row->show_date ? substr((string) $row->show_date, 0, 10) : null, $row->title) : null;
                    if (! $start) {
                        $noTime++;
                        continue;
                    }
                    $filled++;
                    if (! $dry) {
                        DB::table('shows')->where('id', $row->id)->whereNull('start_time')->update(['start_time' => $start]);
                    }
                }
            });

        $still = DB::table('shows')->whereNull('start_time')->count();
        $this->info(($dry ? 'Would fill' : 'Filled')." {$filled} start times; {$noTime} imported shows name no time in their text; {$still} shows have no start time".($dry ? ' (unchanged)' : ' now').'.');

        return self::SUCCESS;
    }
}
