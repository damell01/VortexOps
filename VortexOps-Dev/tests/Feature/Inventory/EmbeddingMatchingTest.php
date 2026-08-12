<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Services\AI\OllamaClient;
use App\Services\EmbeddingService;
use App\Services\ProductMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbeddingMatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake Ollama: returns a deterministic vector per text so we can test the
     * embed → store → rank pipeline without a live server. Vectors are keyed on
     * a signature word so "similar" texts land near each other.
     */
    private function fakeOllama(bool $online = true): void
    {
        $mock = $this->mock(OllamaClient::class);
        $mock->shouldReceive('isOnline')->andReturn($online);
        $mock->shouldReceive('embed')->andReturnUsing(function (string $text) {
            // Simple 3-dim embedding: axis lights up by keyword so cosine
            // similarity groups basketball vs baseball vs pokemon.
            $t = strtolower($text);
            return [
                str_contains($t, 'basketball') || str_contains($t, 'prizm') ? 1.0 : 0.0,
                str_contains($t, 'baseball') || str_contains($t, 'bowman') ? 1.0 : 0.0,
                str_contains($t, 'pokemon') || str_contains($t, 'pokémon') ? 1.0 : 0.0,
            ];
        });
    }

    public function test_embed_product_stores_a_vector(): void
    {
        $this->fakeOllama();
        $product = InventoryItem::create(['name' => '2024 Prizm Basketball Hobby', 'sku' => 'PRZ-1', 'is_active' => true]);

        $ok = app(EmbeddingService::class)->embedProduct($product);

        $this->assertTrue($ok);
        $this->assertNotNull($product->embedding); // relation now
        // (JSON round-trips 1.0 → 1, so compare loosely.)
        $this->assertEquals([1, 0, 0], $product->embedding->vector); // basketball axis
    }

    public function test_embed_product_returns_false_when_ollama_offline(): void
    {
        $this->fakeOllama(online: false); // embed() still mocked, but simulate a null return
        $mock = $this->mock(OllamaClient::class);
        $mock->shouldReceive('embed')->andReturnNull();

        $product = InventoryItem::create(['name' => 'Whatever Box', 'sku' => 'W-1', 'is_active' => true]);
        $this->assertFalse(app(EmbeddingService::class)->embedProduct($product));
        $this->assertFalse($product->embedding()->exists());
    }

    public function test_embedding_stage_matches_the_semantically_closest_product(): void
    {
        $this->fakeOllama();

        // Two embedded products on different axes.
        $bball = InventoryItem::create(['name' => '2024 Prizm Basketball Hobby', 'sku' => 'PRZ-1', 'is_active' => true]);
        \App\Models\ProductEmbedding::create(['product_id' => $bball->id, 'vector' => [1.0, 0.0, 0.0]]);
        $bball2 = InventoryItem::create(['name' => '2024 Bowman Chrome Baseball', 'sku' => 'BOW-1', 'is_active' => true]);
        \App\Models\ProductEmbedding::create(['product_id' => $bball2->id, 'vector' => [0.0, 1.0, 0.0]]);

        // A description with no alias/exact/fuzzy hit (different wording) but
        // clearly basketball → embedding stage should pick the Prizm product.
        $match = app(ProductMatchingService::class)->match('Panini basketball wax');

        $this->assertNotNull($match['product']);
        $this->assertSame($bball->id, $match['product']->id);
        $this->assertSame('embedding', $match['stage']);
    }

    public function test_generate_now_populates_missing_embeddings(): void
    {
        $this->fakeOllama();
        InventoryItem::create(['name' => 'Pokemon SV Booster', 'sku' => 'PKM-1', 'is_active' => true]);
        InventoryItem::create(['name' => '2024 Prizm Basketball', 'sku' => 'PRZ-2', 'is_active' => true]);

        $this->assertSame(0, \App\Models\ProductEmbedding::count());

        $svc = app(EmbeddingService::class);
        Product::where('is_active', true)->whereDoesntHave('embedding')->get()
            ->each(fn (Product $p) => $svc->embedProduct($p));

        $this->assertSame(2, \App\Models\ProductEmbedding::count());
    }
}
