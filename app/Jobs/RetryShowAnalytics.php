<?php
namespace App\Jobs;

use App\Models\Show;
use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiOrderApplier;
use App\Services\WhatnotApiShowAnalyticsApplier;
use App\Services\WhatnotSellerApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        try {
            $show = Show::readyForAnalytics()->find($this->showId);
            if (! $show || ! $show->whatnot_show_id || ! $show->whatnot_channel_id) return;

            $connection = WhatnotApiConnection::query()
                ->where('whatnot_channel_id', $show->whatnot_channel_id)
                ->where('enabled', true)
                ->first();

            if (! $connection || ! $connection->access_token) {
                // Do not silently report a queued retry as successful when no API call was made.
                Log::warning('Show analytics retry skipped: API connection unavailable', ['show_id' => $this->showId]);
                if (Schema::hasColumn('shows', 'analytics_sync_note')) {
                    $show->forceFill(['analytics_sync_note' => 'API retry could not run: channel API connection is disabled or missing a token.'])->save();
                }
                return;
            }

            // Persist the attempt even when Whatnot has not generated analytics yet.
            // The Shows modal reads this stamp instead of displaying "Not checked yet".
            if (WhatnotApiOrderApplier::writesAllowed()) {
                $payloadMeta = $show->raw_import_payload ?? [];
                if (! is_array($payloadMeta)) $payloadMeta = [];
                $payloadMeta['_analytics_last_attempt_at'] = now()->toIso8601String();
                $show->raw_import_payload = $payloadMeta;
                $show->save();
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
            if (WhatnotApiOrderApplier::writesAllowed() && in_array($result['outcome'], ['not_ready', 'not_calculated'], true) && Schema::hasColumn('shows', 'analytics_sync_status')) {
                $show->refresh();
                $show->forceFill([
                    'analytics_sync_status' => $result['outcome'] === 'not_ready' ? 'pending' : 'unavailable',
                    'analytics_sync_note' => $result['outcome'] === 'not_ready' ? 'Whatnot has not generated show analytics yet.' : 'Whatnot will not calculate analytics for this show.',
                ])->save();
            }
            Log::info('Show analytics API retry finished', [
                'show_id' => $this->showId,
                'outcome' => $result['outcome'],
                'writes_allowed' => WhatnotApiOrderApplier::writesAllowed(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Show analytics API retry failed', ['show_id' => $this->showId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
