<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Models\WhatnotSync;
use App\Services\WhatnotDesktopIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatnotCollectorController extends Controller
{
    public function bootstrap(): JsonResponse
    {
        $channels = WhatnotChannel::query()
            ->where('status', 'active')
            ->where('include_in_import', true)
            ->orderBy('id')
            ->get()
            ->map(function (WhatnotChannel $channel) {
                $recent = Show::query()
                    ->where('whatnot_channel_id', $channel->id)
                    ->whereNotNull('detail_url')
                    ->orderByDesc('show_date')
                    ->limit(25)
                    ->get(['id', 'whatnot_show_id', 'detail_url', 'show_date', 'last_synced_at']);

                $latestLiveId = null;
                foreach ($recent as $show) {
                    if ($show->whatnot_show_id && preg_match('/^[0-9a-f-]{36}$/i', (string) $show->whatnot_show_id)) {
                        $latestLiveId = strtolower((string) $show->whatnot_show_id);
                        break;
                    }
                    if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', (string) $show->detail_url, $match)) {
                        $latestLiveId = strtolower($match[0]);
                        break;
                    }
                }

                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'whatnot_username' => $channel->whatnot_username,
                    'latest_live_id' => $latestLiveId,
                    'latest_show_date' => optional($recent->first()?->show_date)->toDateString(),
                    'last_synced_at' => optional($recent->first()?->last_synced_at)->toIso8601String(),
                ];
            })
            ->values();

        // Preserve the existing import fallback: if no channels have the flag
        // enabled, return all active channels instead of making the collector a
        // silent no-op.
        if ($channels->isEmpty()) {
            $channels = WhatnotChannel::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->map(fn (WhatnotChannel $channel) => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'whatnot_username' => $channel->whatnot_username,
                    'latest_live_id' => null,
                    'latest_show_date' => null,
                    'last_synced_at' => null,
                ])
                ->values();
        }

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'channels' => $channels,
            'collector' => [
                'protocol_version' => 1,
                'max_bundle_bytes' => 7_000_000,
            ],
        ]);
    }

    public function import(Request $request, WhatnotDesktopIngestor $ingestor): JsonResponse
    {
        // Keep comfortably below the common 8 MB PHP/nginx request ceiling so a
        // historical backfill fails clearly on the desktop instead of being
        // truncated somewhere before Laravel sees it.
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($contentLength > 7_000_000) {
            return response()->json([
                'ok' => false,
                'error' => 'COLLECTOR_BUNDLE_TOO_LARGE',
                'message' => 'Collector bundle exceeds 7 MB. Reduce batch_size and retry.',
            ], 413);
        }

        $validated = $request->validate([
            'collector_run_id' => ['required', 'string', 'max:100'],
            'collector_version' => ['nullable', 'string', 'max:40'],
            'computer_name' => ['nullable', 'string', 'max:150'],
            'requested_channel_username' => ['required', 'string', 'max:100'],
            'verified_channel_username' => ['required', 'string', 'max:100'],
            'shows' => ['present', 'array', 'max:500'],
            'orders_by_live_id' => ['present', 'array'],
            'shipments_by_live_id' => ['present', 'array'],
            'ledger' => ['present', 'array', 'max:10000'],
            'component_status' => ['nullable', 'array'],
        ]);

        try {
            $result = $ingestor->ingest($validated);

            return response()->json([
                'ok' => true,
                'result' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => 'INVALID_BUNDLE', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'CHANNEL_CONTEXT_MISMATCH') ? 409 : 422;
            return response()->json(['ok' => false, 'error' => 'IMPORT_REJECTED', 'message' => $e->getMessage()], $status);
        }
    }

    public function latest(): JsonResponse
    {
        $rows = WhatnotSync::query()
            ->where('type', 'desktop_collector')
            ->with('channel:id,name,whatnot_username')
            ->latest('started_at')
            ->limit(25)
            ->get();

        return response()->json(['ok' => true, 'syncs' => $rows]);
    }
}
