<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WhatnotScraper;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Opens Seller Hub with the saved Whatnot cookies after they are refreshed
 * from System Health, and tells the person who refreshed them how it went.
 * Queued because a browser check can take a minute or two.
 */
class TestWhatnotSignIn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public int $userId) {}

    public function handle(WhatnotScraper $scraper): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        try {
            $result = $scraper->testCookieAuth();
            Notification::make()->title('Whatnot sign-in works')
                ->body('Seller Hub opened with the new cookies'.(! empty($result['url']) ? ' ('.$result['url'].')' : '').'. Imports will use them on the next run.')
                ->success()->sendToDatabase($user);
        } catch (\Throwable $e) {
            Notification::make()->title('Whatnot sign-in test failed')
                ->body(\Illuminate\Support\Str::limit(strtok($e->getMessage(), "\n") ?: $e->getMessage(), 300).' Export fresh cookies from whatnot.com while signed in and try again.')
                ->danger()->sendToDatabase($user);
        }
    }
}
