<?php

namespace App\Console\Commands;

use App\Notifications\ScraperHealthDigestNotification;
use App\Services\Notifier;
use App\Services\ScraperHealthDigest;
use Illuminate\Console\Command;

/**
 * Monday morning: how the Whatnot import did over the last 7 days, and how
 * long the saved sign-in has left (Settings → Notifications → "Whatnot
 * import health" decides who receives it).
 */
class SendScraperHealthDigest extends Command
{
    protected $signature = 'reports:scraper-health-digest';

    protected $description = 'Email the weekly Whatnot import health digest: imports, failures, gaps and sign-in expiry';

    public function handle(ScraperHealthDigest $digest): int
    {
        $data = $digest->build();
        $sent = Notifier::send('scraper_health_digest', new ScraperHealthDigestNotification($data));
        $this->info(($data['needs_attention'] ? 'Needs attention. ' : 'Healthy. ')."Digest for {$data['period']} sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
