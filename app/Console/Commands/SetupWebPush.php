<?php

namespace App\Console\Commands;

use App\Services\WebPushService;
use Illuminate\Console\Command;

class SetupWebPush extends Command
{
    protected $signature = 'push:setup';
    protected $description = 'Initialize stable encrypted Web Push keys without exposing or rotating them';
    public function handle(WebPushService $push): int
    {
        $push->setup();
        $this->info('Web Push server keys are ready. Devices can opt in from My notifications.');
        return self::SUCCESS;
    }
}
