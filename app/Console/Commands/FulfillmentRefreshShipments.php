<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotApiConnection;
use App\Models\WhatnotShowOrder;
use App\Services\FulfillmentShipmentRefreshService;
use App\Services\WhatnotApiOrderApplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class FulfillmentRefreshShipments extends Command
{
    protected $signature = 'fulfillment:refresh-shipments {--days=14} {--limit=8}';
    protected $description = 'Refresh recent fulfillment shipments from verified enabled Whatnot Seller API connections.';

    public function handle(FulfillmentShipmentRefreshService $refresh): int
    {
        if (! app()->environment('production')
            || ! config('services.whatnot_seller.live_requests_enabled', false)
            || ! config('services.whatnot_seller.contract_verified', false)
            || ! config('services.whatnot_seller.sync_scheduler_enabled', false)
            || ! WhatnotApiOrderApplier::writesAllowed()) {
            $this->line('Fulfillment shipment refresh is disabled by API safety gates.');
            return self::SUCCESS;
        }

        $days = max(1, min(90, (int) $this->option('days')));
        $limit = max(1, min(30, (int) $this->option('limit')));
        $channels = WhatnotApiConnection::query()->where('enabled', true)->where('sync_enabled', true)
            ->pluck('whatnot_channel_id');

        $shows = Show::query()->whereIn('whatnot_channel_id', $channels)
            ->where('show_date', '>=', now()->subDays($days)->toDateString())
            ->whereIn('id', WhatnotShowOrder::query()->select('show_id')->whereNotNull('whatnot_order_id'))
            ->orderByDesc('show_date')->limit($limit)->get();

        $errors = 0;
        foreach ($shows as $show) {
            $lock = Cache::lock('fulfillment-shipments:show:'.$show->id, 900);
            if (! $lock->get()) continue;
            try {
                $result = $refresh->refresh($show);
                $this->line('Show '.$show->id.': '.json_encode($result));
            } catch (Throwable $exception) {
                $errors++;
                report($exception);
                $this->warn('Shipment refresh failed for show '.$show->id);
            } finally {
                $lock->release();
            }
        }
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
