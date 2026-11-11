<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turns a link to a show, an inventory item or a pay run into a small card
 * for the Messages widget: title and one line of detail. Only for records the
 * viewer may open; anything else (or any other link) returns no card.
 */
class ChatLinkPreviewController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowed = config('chatify-preview.available', false)
            && (\App\Services\TeamCommunications::canAccess($user) || config('chatify-preview.team_enabled', false));
        abort_unless($user && $allowed, 403);

        $cards = [];
        foreach (array_slice((array) $request->input('urls', []), 0, 10) as $url) {
            if (! is_string($url) || mb_strlen($url) > 500) continue;
            $card = $this->card($url);
            if ($card) $cards[$url] = $card;
        }

        return response()->json(['cards' => (object) $cards])->header('Cache-Control', 'private, no-store');
    }

    private function card(string $url): ?array
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        if ($host && $host !== request()->getHost()) return null;
        $path = '/' . trim((string) ($parts['path'] ?? ''), '/');

        try {
            if (preg_match('#^/admin/shows/(\d+)(?:/|$)#', $path, $m)) {
                $show = \App\Models\Show::query()->with('channel:id,name')->find((int) $m[1]);
                if (! $show || ! \App\Filament\Resources\ShowResource::canView($show)) return null;

                return ['kind' => 'Show', 'icon' => 'show', 'title' => (string) $show->title, 'url' => $url, 'image' => $show->cover_image_url,
                    'detail' => trim(($show->show_date?->format('D, M j') ?? '') . ($show->channel ? ' · ' . $show->channel->name : ''), ' ·')];
            }
            if (preg_match('#^/admin/inventory-items/(\d+)(?:/|$)#', $path, $m)) {
                $item = \App\Models\InventoryItem::query()->find((int) $m[1]);
                if (! $item || ! \App\Filament\Resources\InventoryItemResource::canView($item)) return null;
                $onHand = (float) \App\Models\InventoryStock::query()->where('inventory_item_id', $item->id)->sum('quantity');

                return ['kind' => 'Item', 'icon' => 'item', 'title' => (string) $item->name, 'url' => $url, 'image' => $item->hasImage() ? $item->imageUrl() : null,
                    'detail' => trim(($item->sku ? 'SKU ' . $item->sku . ' · ' : '') . number_format($onHand) . ' on hand')];
            }
            if (preg_match('#^/admin/weekly-payout-batches/(\d+)(?:/|$)#', $path, $m)) {
                $run = \App\Models\WeeklyPayoutBatch::query()->find((int) $m[1]);
                if (! $run || ! \App\Filament\Resources\WeeklyPayoutBatchResource::canView($run)) return null;

                return ['kind' => 'Pay run', 'icon' => 'pay', 'title' => 'Pay run ' . \Illuminate\Support\Carbon::parse($run->week_start)->format('M j') . ' – ' . \Illuminate\Support\Carbon::parse($run->week_end)->format('M j, Y'), 'url' => $url, 'image' => null,
                    'detail' => ucfirst((string) $run->status)];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
