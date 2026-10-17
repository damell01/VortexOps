<?php

namespace App\Support;

use App\Models\Setting;
use Symfony\Component\Console\Output\Output;

/**
 * Console output for a scraper run started from the Scraper Status page.
 *
 * Artisan::call() only hands back its output once the command returns, which
 * for a reporting pass is hours. This writes each line through to a setting
 * as it happens (at most every couple of seconds), so the page can show what
 * the scraper is doing and pulling while it runs. The recent text is still
 * available afterwards from all().
 */
class WhatnotLiveLog extends Output
{
    public const KEY = 'whatnot_ui_log';
    public const MAX_LINES = 400;

    private string $all = '';

    /** @param string|null $runId the page run this output belongs to; a replaced run stops writing. */
    public function __construct(private ?string $runId = null)
    {
        parent::__construct();
    }
    private float $lastFlush = 0;

    public static function clear(): void
    {
        Setting::set(self::KEY, '');
    }

    /** The most recent lines written, oldest first. */
    public static function lines(): array
    {
        $text = (string) Setting::get(self::KEY, '');

        return $text === '' ? [] : explode("\n", $text);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->all .= $message . ($newline ? "\n" : '');
        if (strlen($this->all) > 400000) {
            // Hours of browser progress add up; only the tail is ever shown.
            $this->all = substr($this->all, -200000);
        }
        if (microtime(true) - $this->lastFlush >= 2) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $this->lastFlush = microtime(true);
        if ($this->runId !== null && (json_decode((string) Setting::get('whatnot_ui_job', '{}'), true)['run_id'] ?? null) !== $this->runId) {
            return;
        }
        $lines = array_values(array_filter(preg_split('/\R/', $this->all) ?: [], fn ($l) => trim($l) !== ''));
        Setting::set(self::KEY, implode("\n", array_slice($lines, -self::MAX_LINES)));
    }

    public function all(): string
    {
        return $this->all;
    }
}
