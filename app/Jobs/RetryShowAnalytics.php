<?php
namespace App\Jobs;

use App\Models\Show;
use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiOrderApplier;
use App\Services\WhatnotApiShowAnalyticsApplier;
use App\Services\WhatnotSellerApiClient;
use App\Support\WhatnotPipelineLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RetryShowAnalytics implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;
    public int $uniqueFor = 300;

    public function __construct(public int $showId) { $this->onQueue('whatnot'); }
    public function uniqueId(): string { return (string) $this->showId; }

    public function handle(WhatnotSellerApiClient $client, WhatnotApiShowAnalyticsApplier $applier): void
    {
        $lock = WhatnotPipelineLock::acquire('Owner API analytics retry for show #'.$this->showId);
        if (! $lock) { $this->release(30); return; }

        try {
            $show = Show::readyForAnalytics()->find($this->showId);
            if (! $show || ! $show->whatnot_show_id || ! $show->whatnot_channel_id) return;

            $connection = WhatnotApiConnection::query()
                ->where('whatnot_channel_id', $show->whatnot_channel_id)
                ->where('enabled', true)
                ->first();

            if (! $connection || ! $connection->access_token) {
                Log::warning('Show analytics retry skipped: API connection unavailable', ['show_id' => $this->showId]);
                return;
            }

            $response = $client->call($connection, 'show_analytics', (string) $show->whatnot_show_id);
            if (! $response->successful()) {
                throw new RuntimeException('Whatnot show analytics API returned HTTP '.$response->status());
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                throw new RuntimeException('Whatnot show analytics API returned invalid JSON');
            }

            $result = $applier->apply((int) $show->whatnot_channel_id, $payload, ! WhatnotApiOrderApplier::writesAllowed());
            Log::info('Show analytics API retry finished', [
                'show_id' => $this->showId,
                'outcome' => $result['outcome'],
                'writes_allowed' => WhatnotApiOrderApplier::writesAllowed(),
            ]);
        } finally {
            WhatnotPipelineLock::release($lock);
        }
    }
}
