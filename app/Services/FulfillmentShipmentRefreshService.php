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
        // Shipments come newest first and a show's labels are only made after
        // it airs, so stop once a page reaches back before the show, or once
        // every order on the show has been found. Hitting the page cap is a
        // partial refresh, not a failure: the next run picks up the rest.
        $since = $show->show_date ? \Illuminate\Support\Carbon::parse($show->show_date)->subDay()->startOfDay() : now()->subDays(30);
        $found = [];
        $page = $this->client->eachPage($connection, 'shipments', function (array $payload) use ($connection, $show, $known, $since, &$found, &$result) {
            $oldest = null;
            foreach ($payload['data'] as $shipment) {
                if (! is_array($shipment) || ! is_string($shipment['id'] ?? null)) continue;
                $at = null;
                if (is_string($shipment['created_at'] ?? null)) {
                    try { $at = \Illuminate\Support\Carbon::parse($shipment['created_at']); $oldest = $oldest === null || $at->lt($oldest) ? $at : $oldest; } catch (\Throwable) {}
                    if ($at !== null && $at->lt($since)) continue;
                }
                $result['checked']++;
                $response = $this->client->call($connection, 'shipment_items', $shipment['id'], ['limit' => 50]);
                if (! $response->successful()) { $result['errors']++; continue; }
                $items = (array) ($response->json('data') ?? []);
                $ours = collect($items)->filter(fn ($item) => is_array($item) && isset($item['order_id']) && $known->has((string) $item['order_id']));
                if ($ours->isEmpty()) continue;
                foreach ($ours as $item) $found[(string) $item['order_id']] = true;
                $result['matched']++;
                $import = $this->importer->import((int) $show->whatnot_channel_id, $shipment, $items, false);
                if ($import['outcome'] === 'updated') $result['updated']++;
            }

            return ! ($oldest !== null && $oldest->lt($since)) && count($found) < $known->count();
        }, maxPages: 20, allowIncomplete: true);
        if (! $page['ok']) {
            throw ValidationException::withMessages(['shipping' => 'Whatnot shipment refresh failed (HTTP '.$page['http_status'].').']);
        }
        $result['incomplete'] = (bool) ($page['incomplete'] ?? false);
        $result['pages'] = $page['pages'];
        return $result;
    }
}
