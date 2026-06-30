<?php

namespace App\Services;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class WhatnotScraper
{
    private string $scriptPath;
    private string $nodeBin;

    public function __construct()
    {
        $this->scriptPath = base_path('scripts/whatnot-scraper.cjs');
        $this->nodeBin    = config('vortex.whatnot.node_bin', 'node');
    }

    /**
     * Run the scraper and return raw show data as an array.
     *
     * @throws \RuntimeException on login/nav failures
     */
    public function fetchShows(int $limit = 50, bool $debug = false): array
    {
        $email    = config('vortex.whatnot.email');
        $password = config('vortex.whatnot.password');

        if (! $email || ! $password) {
            throw new \RuntimeException(
                'WHATNOT_EMAIL and WHATNOT_PASSWORD are not set. Add them to your .env file.'
            );
        }

        $process = new Process(
            [$this->nodeBin, $this->scriptPath],
            null,
            [
                'WHATNOT_EMAIL'    => $email,
                'WHATNOT_PASSWORD' => $password,
                'WHATNOT_LIMIT'    => (string) $limit,
                'WHATNOT_DEBUG'    => $debug ? '1' : '0',
            ]
        );

        $process->setTimeout(180); // 3 min — login + page load can be slow
        $process->run();

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper stderr', ['output' => $stderr]);
        }

        // Exit code 2 = selector miss (selectors need updating, not a credentials error)
        if ($process->getExitCode() === 2) {
            throw new \RuntimeException(
                "Whatnot scraper: show row selectors didn't match the page. " .
                "Set WHATNOT_DEBUG=1 and re-run to capture screenshots. " .
                "Check logs for a PAGE_SNAPSHOT to update scripts/whatnot-scraper.cjs."
            );
        }

        if (! $process->isSuccessful()) {
            $message = $stderr ?: "Scraper exited with code {$process->getExitCode()}";
            throw new \RuntimeException("Whatnot scraper failed: {$message}");
        }

        if (empty($stdout)) {
            return [];
        }

        $data = json_decode($stdout, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Whatnot scraper returned invalid JSON: ' . json_last_error_msg());
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Fetch shows and upsert them into the shows table.
     * Returns counts: ['created' => n, 'updated' => n, 'skipped' => n].
     */
    public function importShows(?WhatnotChannel $channel = null, int $limit = 50, bool $debug = false): array
    {
        $rows = $this->fetchShows($limit, $debug);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (empty($row['title']) && empty($row['show_date'])) {
                $skipped++;
                continue;
            }

            // Try to match an existing show by title + date to avoid duplicates.
            // If neither is present, skip.
            $lookupTitle = $row['title'] ? trim($row['title']) : null;
            $lookupDate  = $row['show_date'] ?? null;

            $query = Show::query()->where('import_source', 'auto_whatnot');

            if ($lookupTitle && $lookupDate) {
                $query->where('title', $lookupTitle)->where('show_date', $lookupDate);
            } elseif ($lookupTitle) {
                $query->where('title', $lookupTitle);
            } elseif ($lookupDate) {
                $query->where('show_date', $lookupDate);
            } else {
                $skipped++;
                continue;
            }

            $existing = $query->first();

            $payload = array_filter([
                'whatnot_channel_id' => $channel?->id,
                'title'              => $lookupTitle,
                'show_date'          => $lookupDate,
                'show_duration'      => $row['show_duration'] ?? null,
                'gross_revenue'      => $row['gross_revenue'] ?? null,
                'whatnot_net'        => $row['whatnot_net'] ?? null,
                'tips'               => $row['tips'] ?? null,
                'units_sold'         => $row['units_sold'] ?? null,
                'import_source'      => 'auto_whatnot',
            ], fn ($v) => $v !== null);

            if ($existing) {
                // Only update financial fields on already-imported shows —
                // don't overwrite manually entered status/notes/streamers.
                $financial = array_intersect_key($payload, array_flip([
                    'gross_revenue', 'whatnot_net', 'tips', 'units_sold', 'show_duration',
                ]));

                if (! empty($financial)) {
                    $existing->update($financial);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                Show::create(array_merge($payload, ['status' => 'draft', 'created_by' => auth()->id() ?? 1]));
                $created++;
            }
        }

        Log::info('WhatnotScraper import complete', compact('created', 'updated', 'skipped'));

        return compact('created', 'updated', 'skipped');
    }
}
