<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Support\ShowTitleTags;
use Illuminate\Console\Command;

/**
 * Tags shows from their titles (giveaways, $1 starts, free packs…) so the
 * Show Formats report can compare earnings per hour by what a show offered.
 *
 * Runs nightly. Only shows never tagged, or tagged by an older version of the
 * rules, are touched; --all re-tags everything. Writes skip model events —
 * tags are derived data, not an edit anyone made to the show.
 */
class TagShowTitles extends Command
{
    protected $signature = 'shows:tag-titles {--all : Re-tag every show, not just untagged or outdated ones}';

    protected $description = 'Tag shows from their titles (giveaways, $1 starts, free packs) for the format report';

    public function handle(): int
    {
        $tagged = 0;

        Show::query()
            ->when(! $this->option('all'), fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('title_tags_version')->orWhere('title_tags_version', '<', ShowTitleTags::VERSION)))
            ->select(['id', 'title'])
            ->chunkById(500, function ($shows) use (&$tagged) {
                foreach ($shows as $show) {
                    Show::whereKey($show->id)->update([
                        'title_tags' => json_encode(ShowTitleTags::for($show->title)),
                        'title_tags_version' => ShowTitleTags::VERSION,
                    ]);
                    $tagged++;
                }
            });

        $this->info("Tagged {$tagged} show(s).");

        return self::SUCCESS;
    }
}
