<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\StreamerResource\Pages\CreateStreamer;
use App\Filament\Resources\StreamerResource\Pages\EditStreamer;
use App\Models\Streamer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CreateRecord/EditRecord wrap the resource form() in a 2-column outer grid
 * by default (Filament\Resources\Pages\CreateRecord::defaultForm()). A
 * top-level Section without ->columnSpanFull() only ever fills one of those
 * two columns — a real half-width bug, not just a density issue. Covers the
 * resources that were touched (but not actually fixed) in an earlier
 * "widen narrow forms" pass.
 */
class FormFullWidthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');

        return $u;
    }

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function assertSectionSpansFullWidth(string $html, string $sectionKeyFragment): void
    {
        // The section's wrapping fi-grid-col carries the col-span style right
        // before its own wire:key — match the pair so we're checking the
        // correct Section, not just any full-width element on the page.
        $this->assertMatchesRegularExpression(
            '/--col-span-default: 1 \/ -1;"\s+wire:key="[^"]*' . preg_quote($sectionKeyFragment, '/') . '[^"]*"/',
            $html,
        );
    }

    public function test_create_inventory_item_form_is_full_width(): void
    {
        $this->actingAs($this->admin());

        $html = $this->get('/admin/inventory-items/create')->getContent();

        $this->assertSectionSpansFullWidth($html, 'item-details');
    }

    public function test_create_vendor_form_is_full_width(): void
    {
        $this->actingAs($this->admin());

        $html = $this->get('/admin/vendors/create')->getContent();

        $this->assertSectionSpansFullWidth($html, 'vendor-details');
    }

    public function test_edit_vendor_form_is_full_width(): void
    {
        $this->actingAs($this->admin());
        $vendor = Vendor::create(['name' => 'Acme', 'status' => 'active']);

        $html = $this->get("/admin/vendors/{$vendor->id}/edit")->getContent();

        $this->assertSectionSpansFullWidth($html, 'vendor-details');
    }

    public function test_adp_employee_id_field_does_not_appear_on_create_streamer_form(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->assertDontSee('ADP Employee ID');
    }

    public function test_adp_employee_id_field_does_not_appear_on_edit_streamer_form(): void
    {
        Livewire::actingAs($this->admin());
        $streamer = Streamer::create(['name' => 'Test Streamer', 'status' => 'active']);

        Livewire::test(EditStreamer::class, ['record' => $streamer->getRouteKey()])
            ->assertDontSee('ADP Employee ID');
    }
}
