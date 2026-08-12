<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Reports;
use App\Filament\Resources\FeatureFlagResource\Pages\ListFeatureFlags;
use App\Models\FeatureFlag;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Payout;
use App\Models\ProductIdentity;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Real bugs found during a site-wide click-through crawl:
 *
 * 1. Reports::getPayoutStatusSummaryProperty() called
 *    ->pluck(null, 'status') — invalid Query Builder usage (pluck's first
 *    arg must be a column name) that 500'd the instant a payout existed in
 *    the selected period. ->get()->keyBy('status') is what the surrounding
 *    code (which reads ->cnt / ->total off each row) actually needed.
 * 2. FeatureFlagResource imported EditAction from Filament\Tables\Actions,
 *    a namespace that doesn't exist in this Filament v5 install (same
 *    mistake fixed earlier in CommentsRelationManager) — the row action
 *    would fatal the moment anyone with developer_email access tried to
 *    edit a flag's description.
 * 3. Three blade views used the @php(expr) one-line shorthand directive
 *    (view-payout.blade.php, view-inventory-movement.blade.php,
 *    payout-preview.blade.php). It compiles to an unclosed <?php tag in this
 *    Blade/Livewire setup, swallowing every directive after it into one
 *    invalid PHP fragment — a ParseError on the *compiled* file, so it only
 *    surfaces the first time that page is actually rendered (view:clear
 *    does not "fix" it, it recompiles the exact same broken output).
 *    Rewritten as @php / @endphp blocks.
 * 4. Product::lots() and Product::identities() didn't specify an explicit
 *    foreign key. Eloquent's default-FK convention is based on the calling
 *    instance's *runtime* class, and InventoryItem extends Product — so
 *    calling ->lots() on an InventoryItem inferred "inventory_item_id"
 *    instead of the real column, "product_id" (inventory_lots and
 *    product_identities both use product_id). Every other relation on
 *    Product already worked around this with an explicit FK — including a
 *    comment on embedding() calling out exactly this trap — lots() and
 *    identities() just never got the same treatment. Broke viewing any
 *    single inventory item (ViewInventoryItem eager-loads 'lots').
 */
class SiteWideCrawlFixesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
    }

    public function test_reports_payout_status_summary_does_not_crash_with_data_in_every_status(): void
    {
        $this->actingAs($this->owner());
        $creator = User::factory()->create();
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);

        foreach (['draft', 'approved', 'paid'] as $status) {
            $show = Show::create([
                'title' => "Show {$status}", 'show_date' => now()->toDateString(),
                'status' => 'reconciled', 'created_by' => $creator->id,
            ]);
            Payout::create([
                'show_id' => $show->id, 'streamer_id' => $streamer->id,
                'payout_type' => 'flat_rate', 'status' => $status, 'calculated_payout' => 42.0,
            ]);
        }

        $page = new Reports();
        $summary = $page->payoutStatusSummary;

        $this->assertSame(1, $summary['draft']['count']);
        $this->assertSame(1, $summary['approved']['count']);
        $this->assertSame(1, $summary['paid']['count']);
        $this->assertEquals(42.0, $summary['paid']['total']);
    }

    public function test_feature_flag_edit_action_uses_a_real_filament_action_class(): void
    {
        $devUser = User::factory()->create(['email' => 'dev@test.com']);
        config(['app.developer_email' => 'dev@test.com']);
        $this->actingAs($devUser);

        FeatureFlag::create(['key' => 'test_flag', 'label' => 'Test Flag', 'description' => 'Old desc', 'enabled' => false]);

        Livewire::test(ListFeatureFlags::class)
            ->assertOk()
            ->assertSuccessful();
    }

    public function test_view_payout_page_renders_without_a_parse_error(): void
    {
        $this->actingAs($this->owner());
        $creator = User::factory()->create();
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        $payout = Payout::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'payout_type' => 'flat_rate', 'status' => 'paid', 'calculated_payout' => 42.0]);

        $this->get("/admin/payouts/{$payout->id}")->assertOk();
    }

    public function test_view_inventory_movement_page_renders_without_a_parse_error(): void
    {
        $this->actingAs($this->owner());
        $item = InventoryItem::create(['sku' => 'SKU-X', 'name' => 'Item X', 'unit_cost' => 1, 'is_active' => true]);
        $movement = InventoryMovement::create(['inventory_item_id' => $item->id, 'quantity' => 1, 'movement_type' => 'opening']);

        $this->get("/admin/inventory-movements/{$movement->id}")->assertOk();
    }

    public function test_payout_preview_view_renders_without_a_parse_error(): void
    {
        $html = view('filament.payout-preview', [
            'preview' => [
                'rows' => [
                    ['streamer' => 'Test Streamer', 'gross' => 100.0, 'loan' => 10.0, 'net' => 90.0],
                ],
                'gross' => 100.0,
                'loan' => 10.0,
                'net' => 90.0,
            ],
        ])->render();

        $this->assertStringContainsString('Test Streamer', $html);
    }

    public function test_view_inventory_item_page_renders_without_a_wrong_column_error(): void
    {
        $this->actingAs($this->owner());
        $item = InventoryItem::create(['sku' => 'SKU-Y', 'name' => 'Item Y', 'unit_cost' => 1, 'is_active' => true]);
        InventoryLot::create(['product_id' => $item->id, 'quantity' => 5, 'unit_cost' => 2, 'remaining_quantity' => 5]);
        ProductIdentity::create(['product_id' => $item->id, 'type' => ProductIdentity::TYPE_ALIAS, 'value' => 'alias']);

        $this->get("/admin/inventory-items/{$item->id}")->assertOk();
    }

    public function test_product_lots_and_identities_use_the_product_id_column(): void
    {
        $item = InventoryItem::create(['sku' => 'SKU-Z', 'name' => 'Item Z', 'unit_cost' => 1, 'is_active' => true]);
        $lot = InventoryLot::create(['product_id' => $item->id, 'quantity' => 3, 'unit_cost' => 1, 'remaining_quantity' => 3]);
        $identity = ProductIdentity::create(['product_id' => $item->id, 'type' => ProductIdentity::TYPE_ALIAS, 'value' => 'x']);

        $this->assertTrue($item->lots()->get()->contains('id', $lot->id));
        $this->assertTrue($item->identities()->get()->contains('id', $identity->id));
    }
}
