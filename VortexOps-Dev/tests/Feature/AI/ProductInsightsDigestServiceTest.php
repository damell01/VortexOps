<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Services\AI\ProductInsightsDigestService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductInsightsDigestServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private Show $show;
    private InventoryLocation $loc;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        $this->creator = User::factory()->create();
        $this->actingAs($this->creator);
        $this->show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $this->creator->id]);
        $this->loc  = InventoryLocation::create(['name' => 'Main']);
    }

    /**
     * Setting::set('enabled_admin_modules', ...) forgets the cache key at
     * write time, but RefreshDatabase only rolls back the DB — the cache
     * store isn't request/test-scoped, so a narrowed module list written
     * here would otherwise leak into whatever test runs next in the same
     * PHPUnit process and 403 it. Forget the key again post-test so the
     * next reader re-queries the (rolled-back) DB instead of a stale cache.
     */
    protected function tearDown(): void
    {
        Cache::forget('setting:enabled_admin_modules');
        AdminModules::flushMemo();
        parent::tearDown();
    }

    private function enableModules(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams', 'ai', 'inventory']));
        AdminModules::flushMemo();
    }

    private function itemWithStockAndSale(string $name = 'Hot Box'): InventoryItem
    {
        $item = InventoryItem::create(['name' => $name, 'unit_cost' => 5, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $this->loc->id, 'quantity' => 10]);
        WhatnotShowOrder::create([
            'show_id' => $this->show->id, 'inventory_item_id' => $item->id, 'buyer_username' => 'b',
            'item_name' => $name, 'quantity' => 2, 'unit_cost' => 5, 'total_price' => 20,
            'status' => 'completed', 'show_date' => now()->toDateString(),
        ]);

        return $item;
    }

    /** Same fake-provider-through-the-real-gateway pattern as PalletSlipParserTest/OpsDigestServiceTest. */
    private function fakeProvider(?string $chatReturn, bool $healthy = true): object
    {
        $fake = new class($chatReturn, $healthy) implements AIProvider {
            public array $lastChat = [];
            public bool $chatCalled = false;

            public function __construct(private ?string $chatReturn, private bool $healthy) {}

            public function name(): string { return 'fake'; }

            public function chat(array $messages, string $model, array $options = []): string
            {
                $this->chatCalled = true;
                $this->lastChat = compact('messages', 'model', 'options');

                return $this->chatReturn ?? '';
            }

            public function stream(array $m, string $model, array $o = []): \Generator { yield ''; }
            public function vision(string $p, string $i, string $model, array $o = []): string { return ''; }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return $this->healthy; }
        };
        $this->app->instance(AIProvider::class, $fake);

        return $fake;
    }

    public function test_returns_null_when_ai_module_disabled(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams', 'inventory']));
        AdminModules::flushMemo();
        $this->itemWithStockAndSale();

        $fake = $this->fakeProvider('A summary.');

        $this->assertNull(app(ProductInsightsDigestService::class)->generate());
        $this->assertFalse($fake->chatCalled);
    }

    public function test_returns_null_when_inventory_module_disabled(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams', 'ai']));
        AdminModules::flushMemo();
        $this->itemWithStockAndSale();

        $fake = $this->fakeProvider('A summary.');

        $this->assertNull(app(ProductInsightsDigestService::class)->generate());
        $this->assertFalse($fake->chatCalled);
    }

    public function test_returns_null_when_nothing_to_summarize(): void
    {
        $this->enableModules();

        $fake = $this->fakeProvider('A summary.');

        $this->assertNull(app(ProductInsightsDigestService::class)->generate());
        $this->assertFalse($fake->chatCalled);
    }

    public function test_returns_null_when_provider_offline(): void
    {
        $this->enableModules();
        $this->itemWithStockAndSale();

        $fake = $this->fakeProvider('A summary.', healthy: false);

        $this->assertNull(app(ProductInsightsDigestService::class)->generate());
        $this->assertFalse($fake->chatCalled);
    }

    public function test_returns_generated_narrative_and_routes_through_reasoning(): void
    {
        $this->enableModules();
        $this->itemWithStockAndSale();
        Setting::set('ollama_reasoning_model', 'llama3.1:8b');

        $fake = $this->fakeProvider("  Your catalogue is healthy.  \n");

        $result = app(ProductInsightsDigestService::class)->generate();

        $this->assertSame('Your catalogue is healthy.', $result);
        $this->assertSame('llama3.1:8b', $fake->lastChat['model']);
        $this->assertSame(90, $fake->lastChat['options']['timeout']);
    }

    public function test_prompt_mentions_the_actual_product_name(): void
    {
        $this->enableModules();
        $this->itemWithStockAndSale('Very Distinctive Product Name');

        $fake = $this->fakeProvider('ok');

        app(ProductInsightsDigestService::class)->generate();

        $this->assertStringContainsString('Very Distinctive Product Name', $fake->lastChat['messages'][0]['content']);
    }

    public function test_returns_null_for_empty_generated_text(): void
    {
        $this->enableModules();
        $this->itemWithStockAndSale();

        $this->fakeProvider("   \n");

        $this->assertNull(app(ProductInsightsDigestService::class)->generate());
    }
}
