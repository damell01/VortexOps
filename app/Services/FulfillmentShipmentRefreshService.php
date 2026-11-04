<?php

namespace App\Services;

use App\Models\Show;
use App\Models\WhatnotApiConnection;
use App\Models\WhatnotShowOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refresh shipping for a single show without touching payroll or the full
 * Whatnot ledger. Only calls the documented read endpoints.
 */
final class FulfillmentShipmentRefreshService
{
    public function __construct(
        private readonly WhatnotSellerApiClient $client,
        private readonly WhatnotApiShipmentImporter $importer,
    ) {}

    public function refresh(Show $show): array
    {
        $connection = WhatnotApiConnection::query()
            ->where('whatnot_channel_id', $show->whatnot_channel_id)
            ->where('enabled', true)->where('sync_enabled', true)->first();

        if (! $connection || ! WhatnotApiOrderApplier::writesAllowed()) {
            throw ValidationException::withMessages([
                'shipping' => 'The verified Whatnot connection and API import permissions must be enabled before refreshing shipments.',
            ]);
        }

        $known = WhatnotShowOrder::query()->where('show_id', $show->id)
            ->whereNotNull('whatnot_order_id')->pluck('whatnot_order_id')
            ->map(fn ($id) => (string) $id)->unique()->flip();
        if ($known->isEmpty()) {
            throw ValidationException::withMessages(['shipping' => 'Sync show orders before refreshing shipments.']);
        }

        $result = ['checked' => 0, 'matched' => 0, 'updated' => 0, 'errors' => 0];
        $page = $this->client->eachPage($connection, 'shipments', function (array $payload) use ($connection, $show, $known, &$result) {
            foreach ($payload['data'] as $shipment) {
                if (! is_array($shipment) || ! is_string($shipment['id'] ?? null)) continue;
                $result['checked']++;
                $response = $this->client->call($connection, 'shipment_items', $shipment['id'], ['limit' => 50]);
                if (! $response->successful()) { $result['errors']++; continue; }
                $items = (array) ($response->json('data') ?? []);
                if (! collect($items)->contains(fn ($item) => is_array($item) && isset($item['order_id']) && $known->has((string) $item['order_id']))) continue;
                $result['matched']++;
                $import = $this->importer->import((int) $show->whatnot_channel_id, $shipment, $items, false);
                if ($import['outcome'] === 'updated') $result['updated']++;
            }
            return true;
        }, maxPages: 20);
        if (! $page['ok']) {
            throw ValidationException::withMessages(['shipping' => 'Whatnot shipment refresh failed (HTTP '.$page['http_status'].').']);
        }
        $result['pages'] = $page['pages'];
        return $result;
    }
}
