<?php

namespace App\Http\Controllers;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use App\Services\WhatnotApiHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatnotApiHealthController extends Controller
{
    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->isOwner(), 403);
    }

    public function index(Request $request, WhatnotApiHealth $health): JsonResponse
    {
        $this->authorizeOwner($request);
        return response()->json(['connections' => $health->overview(), 'scraper_unchanged' => true]);
    }

    public function save(Request $request, WhatnotChannel $channel): JsonResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate(['token' => ['nullable', 'string', 'max:8192']]);
        $connection = WhatnotApiConnection::firstOrNew(['whatnot_channel_id' => $channel->id]);
        if (filled($data['token'] ?? null)) {
            $connection->access_token = $data['token'];
            $connection->status = 'pending_test';
        }
        $connection->enabled = false;
        $connection->sync_enabled = false;
        $connection->save();

        return response()->json(['saved' => true, 'configured' => filled($connection->access_token)]);
    }

    public function test(Request $request, WhatnotChannel $channel, WhatnotApiHealth $health): JsonResponse
    {
        $this->authorizeOwner($request);
        $connection = WhatnotApiConnection::where('whatnot_channel_id', $channel->id)->firstOrFail();
        return response()->json($health->check($connection));
    }
}
