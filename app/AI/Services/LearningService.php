<?php

namespace App\AI\Services;

use App\Models\ProductEmbedding;
use App\Models\ProductIdentity;
use Illuminate\Support\Facades\Schema;

/**
 * Reports what the matching system has learned. Every human-confirmed match
 * records an alias (ProductIdentity) that makes the next identical shipment an
 * instant, zero-cost hit — this surfaces how much of that knowledge has
 * accumulated, so the "gets smarter over time" claim is measurable.
 */
final class LearningService
{
    /**
     * @return array{aliases:int, confirmations:int, embedded:int, top_aliases:list<array{value:string, times_confirmed:int}>}
     */
    public function stats(): array
    {
        if (! Schema::hasTable('product_identities')) {
            return ['aliases' => 0, 'confirmations' => 0, 'embedded' => 0, 'top_aliases' => []];
        }

        $aliasQuery = ProductIdentity::query()
            ->whereIn('type', [ProductIdentity::TYPE_ALIAS, ProductIdentity::TYPE_SEARCH_TOKEN]);

        return [
            'aliases'       => (clone $aliasQuery)->count(),
            'confirmations' => (int) (clone $aliasQuery)->sum('times_confirmed'),
            'embedded'      => Schema::hasTable('product_embeddings') ? ProductEmbedding::count() : 0,
            'top_aliases'   => (clone $aliasQuery)
                ->where('times_confirmed', '>', 0)
                ->orderByDesc('times_confirmed')
                ->limit(5)
                ->get(['value', 'times_confirmed'])
                ->map(fn (ProductIdentity $i) => [
                    'value'           => (string) $i->value,
                    'times_confirmed' => (int) $i->times_confirmed,
                ])
                ->all(),
        ];
    }
}
