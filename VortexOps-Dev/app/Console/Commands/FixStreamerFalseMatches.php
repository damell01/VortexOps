<?php

namespace App\Console\Commands;

use App\Models\Show;
use Illuminate\Console\Command;

/**
 * One-off cleanup for the detectStreamers() word-boundary bug: a short
 * streamer name (e.g. "Ty") could substring-match inside a longer one
 * attached to the same show (e.g. "Tyler"), so both got auto-attached.
 * Finds shows where one attached streamer's name is a literal substring of
 * another attached streamer's name, and the shorter one doesn't actually
 * stand alone as a word in the show title — i.e. it could only have been
 * attached by the old buggy substring match, not a real word-boundary hit.
 *
 * Dry-run by default; --apply actually detaches the false matches.
 */
class FixStreamerFalseMatches extends Command
{
    protected $signature = 'whatnot:fix-streamer-false-matches {--apply : Actually detach the false matches instead of just reporting them}';

    protected $description = 'Find and remove streamers that were auto-attached to a show only because their short name substring-matched a longer attached streamer\'s name (e.g. "Ty" inside "Tyler")';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $shows = Show::with('streamers')
            ->has('streamers', '>=', 2)
            ->get();

        $fixedCount = 0;
        $rows       = [];

        foreach ($shows as $show) {
            $title = strtolower($show->title ?? '');
            if ($title === '') {
                continue;
            }

            $streamers = $show->streamers;

            foreach ($streamers as $short) {
                $shortName = strtolower($short->name);

                foreach ($streamers as $long) {
                    if ($short->id === $long->id) {
                        continue;
                    }

                    $longName = strtolower($long->name);

                    // $short's name must be a genuine substring of $long's name
                    // (the exact shape of the "Ty" inside "Tyler" bug) — not
                    // just two unrelated names that happen to both be in the title.
                    if ($shortName === $longName || ! str_contains($longName, $shortName)) {
                        continue;
                    }

                    $shortStandsAlone = preg_match('/\b' . preg_quote($shortName, '/') . '\b/u', $title) === 1;
                    $longStandsAlone  = preg_match('/\b' . preg_quote($longName, '/') . '\b/u', $title) === 1;

                    // Only remove $short if it could NOT have matched under the
                    // fixed word-boundary logic, and $long legitimately did —
                    // that's precisely "only the old substring bug could have
                    // attached this." If $short also stands alone on its own
                    // (e.g. title genuinely says "Ty" somewhere else too),
                    // leave both attached — that's a real double-booking, not
                    // the bug.
                    if (! $shortStandsAlone && $longStandsAlone) {
                        $rows[] = [$show->id, $show->title, $short->name, $long->name];

                        if ($apply) {
                            $show->streamers()->detach($short->id);
                        }

                        $fixedCount++;
                    }
                }
            }
        }

        if (empty($rows)) {
            $this->info('No false-match pairs found — nothing to clean up.');
            return self::SUCCESS;
        }

        $this->table(['Show ID', 'Title', 'False match (removing)', 'Kept'], $rows);

        $this->newLine();
        $this->info(
            $apply
                ? "Detached {$fixedCount} false match(es)."
                : "{$fixedCount} false match(es) found — re-run with --apply to actually detach them."
        );

        return self::SUCCESS;
    }
}
