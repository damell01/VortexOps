<?php

namespace App\Console\Commands;

use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

/**
 * Find out what the Seller Hub asks for, so the scraper can ask for it too.
 *
 * The pages this account needs are refused and the API is not, so the route is
 * to call the API directly rather than to load pages and read them. That needs
 * the operation names, and they are in the bundles /seller already loads —
 * same-origin static assets on the surface that answers.
 *
 * Read-only. It opens one page, watches what that page asks for, reads the
 * scripts it loaded, and writes down what it saw.
 */
class WhatnotDiscoverApi extends Command
{
    protected $signature = 'whatnot:discover-api
                            {--save= : Write the full findings to this file}
                            {--filter=* : Only show operations whose name contains one of these}
                            {--find= : Search the bundles for this literal and show what surrounds it}';

    protected $description = 'List the API operations the Seller Hub uses, for building calls against';

    /** What a break business actually wants out of Whatnot. */
    private const INTERESTING = [
        'show', 'livestream', 'live', 'order', 'sale', 'seller',
        'payout', 'earning', 'product', 'listing', 'auction', 'analytics',
    ];

    public function handle(WhatnotScraper $scraper): int
    {
        $this->line('Opening /seller and watching what it asks for…');
        $this->line('<fg=gray>Read-only: one page load, no navigation, nothing written to Whatnot.</>');
        $this->newLine();

        try {
            $found = $scraper->discoverApi(find: $this->option('find'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $operations = $found['operations'] ?? [];
        $liveCalls  = $found['liveCalls'] ?? [];

        // The needle result counts as a finding, and a decisive one: it is the
        // answer to "are we reading the right files at all". Returning early on
        // an empty operation list would swallow the very output the run was
        // asked for.
        if ($operations === [] && $liveCalls === []
            && empty($found['introspection'])
            && blank($found['needle'] ?? null)) {
            $this->warn('Nothing found. Either the page did not finish loading, or the bundles');
            $this->line('are served from a host the fetch could not reach.');

            return self::FAILURE;
        }

        // What the page actually called is worth more than what the bundles
        // mention: these are known to work, with this session, right now.
        if ($liveCalls !== []) {
            $this->info('Calls this page made (' . count($liveCalls) . '):');

            foreach (array_slice($liveCalls, 0, 25) as $call) {
                $this->line(sprintf(
                    '  <fg=gray>%s</> %s',
                    $call['method'] ?? 'GET',
                    str_replace('https://www.whatnot.com', '', $call['url'] ?? ''),
                ));
            }

            $this->newLine();
        }

        // Introspection first: it is the schema answering for itself, where
        // scraping minified JavaScript is guesswork about what a bundler left
        // behind. When it is switched off, saying so is what stops the empty
        // operation list reading as a broken command.
        $schema = $found['introspection'] ?? null;

        if ($schema) {
            if (! empty($schema['fields'])) {
                $this->info('Introspection is enabled — ' . count($schema['fields']) . ' queries the API accepts.');
            } else {
                $this->line('<fg=gray>Introspection: ' . ($schema['error'] ?: 'no schema returned')
                    . ' [' . ($schema['status'] ?? 'no response') . ']</>');
            }

            $this->newLine();
        }

        if (($found['scriptCount'] ?? 0) === 0 && $operations === []) {
            $this->line('<fg=gray>No scripts were referenced by the page, so there were no bundles to read.</>');
            $this->newLine();
        }

        // Whether we are reading the right files at all.
        //
        // "Found nothing" and "read the wrong files" produce the same empty
        // list, and three rounds were spent on the second while assuming the
        // first. Given a name known to exist, this settles which it is — and
        // shows the syntax the patterns have to match.
        if (filled($found['needle'] ?? null)) {
            $hits = $found['needleHits'] ?? [];

            if ($hits === []) {
                $this->warn("\"{$found['needle']}\" is in none of the bundles that were read.");
                $this->line('So the operation text lives somewhere these files are not — the discovery');
                $this->line('is looking in the wrong place rather than finding nothing.');
            } else {
                $this->info("\"{$found['needle']}\" found in " . count($hits) . ' bundle(s):');

                foreach (array_slice($hits, 0, 3) as $hit) {
                    $this->newLine();
                    $this->line("  <fg=gray>{$hit['from']}</>");
                    $this->line('  ' . str_replace("\n", ' ', $hit['context']));
                }
            }

            $this->newLine();
        }

        $filters = $this->option('filter') ?: self::INTERESTING;

        $relevant = array_values(array_filter(
            $operations,
            fn ($op) => collect($filters)->contains(
                fn ($needle) => str_contains(strtolower($op['name'] ?? ''), strtolower($needle)),
            ),
        ));

        // How much was actually read. "69 operations" means nothing without it:
        // the same number can come from one page's chunks or from four hundred,
        // and only the second is evidence that the show queries are not there.
        $this->line(sprintf(
            '<fg=gray>Read %d chunk(s)%s.</>',
            $found['chunksScanned'] ?? 0,
            ($found['buildId'] ?? null) ? '' : ' — no buildId, so chunks were found by following references',
        ));

        $this->info(count($operations) . ' operation(s) in the bundles, ' . count($relevant) . ' worth a look:');

        foreach ($relevant as $op) {
            $this->line(sprintf('  <fg=green>%s</> <fg=gray>(%s)</>', $op['name'], $op['kind'] ?? '?'));
        }

        if ($relevant === [] && $operations !== []) {
            $this->line('  <fg=gray>None matched the filter. Run with --filter= to see everything.</>');
        }

        if ($path = $this->option('save')) {
            file_put_contents($path, json_encode($found, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();
            $this->line("Full findings written to {$path}");
        }

        $this->newLine();
        $this->line('Next: pick the operation that lists shows, and the scraper can call it');
        $this->line('from inside /seller without ever asking for another page.');

        return self::SUCCESS;
    }
}
