<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShow;
use App\Services\ShowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShowImportController extends Controller
{
    public function __construct(private ShowService $showService) {}

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'          => 'nullable|string|max:255',
            'show_date'      => 'nullable|date',
            'started_at'     => 'nullable|date',
            'ended_at'       => 'nullable|date',
            'channel_id'     => 'nullable|integer|exists:whatnot_channels,id',
            'streamer_ids'   => 'nullable|array',
            'streamer_ids.*' => 'integer|exists:streamers,id',
            'source'         => 'nullable|in:manual,csv_import,scraper',
            'raw_data'       => 'nullable|array',
            'sales'          => 'nullable|array',
            'sales.*.item_name'      => 'required_with:sales|string',
            'sales.*.sku'            => 'nullable|string',
            'sales.*.quantity'       => 'nullable|numeric|min:0.01',
            'sales.*.sale_price'     => 'nullable|numeric|min:0',
            'sales.*.buyer_username' => 'nullable|string',
            'sales.*.buyer_name'     => 'nullable|string',
            'sales.*.order_id'       => 'nullable|string',
            'sales.*.sale_type'      => 'nullable|in:break_slot,fixed_price,auction,other',
            'sales.*.sold_at'        => 'nullable|date',
            'financials'                          => 'nullable|array',
            'financials.gross_sales'              => 'nullable|numeric',
            'financials.platform_fee_pct'         => 'nullable|numeric',
            'financials.shipping_collected'       => 'nullable|numeric',
            'financials.tips_collected'           => 'nullable|numeric',
            'financials.owner_platform_fee_pct'   => 'nullable|numeric',
        ]);

        try {
            $validated['source'] = $validated['source'] ?? 'scraper';
            $show = $this->showService->importFromArray($validated);

            Log::info("Show imported via API: Show #{$show->id} ({$show->title}) from {$validated['source']}");

            return response()->json([
                'success' => true,
                'show_id' => $show->id,
                'message' => "Show created with {$show->sales()->count()} sale(s).",
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Show import failed: ' . $e->getMessage());
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    public function show(WhatnotShow $show): JsonResponse
    {
        return response()->json($show->load(['streamers', 'sales', 'financial', 'deductionRequests']));
    }

    public function channels(): JsonResponse
    {
        return response()->json(WhatnotChannel::where('status', 'active')->get(['id', 'name', 'whatnot_username']));
    }

    public function streamers(): JsonResponse
    {
        return response()->json(Streamer::where('status', 'active')->get(['id', 'name', 'payout_type']));
    }
}
