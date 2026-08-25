<?php

namespace Tests\Feature\Tables;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Filament\Resources\VendorResource\Pages\ListVendors;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\TableFilterPresentation;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The rules in TableFilterPresentation, checked against real tables rather
 * than the class in isolation — the point of the global Table::configureUsing
 * is that pages nobody edited pick it up, and only a rendered page proves that.
 */
class FilterPresentationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        // Both pages under test sit behind a module toggle; without these the
        // component never mounts and every assertion below reads as a null.
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
    }

    public function test_a_table_with_many_filters_opens_them_in_a_dialog(): void
    {
        $table = Livewire::test(ListInventoryItems::class)->instance()->getTable();

        $this->assertGreaterThan(2, count($table->getFilters()));
        $this->assertSame(FiltersLayout::Modal, $table->getFiltersLayout());
        // Single-column would put eight filters in a column taller than the
        // window, which is the problem the dialog is here to solve.
        $this->assertSame(['default' => 1, 'md' => 2, 'xl' => 3], $table->getFiltersFormColumns());
        $this->assertSame(Width::FiveExtraLarge, $table->getFiltersFormWidth());
    }

    public function test_a_table_with_few_filters_keeps_the_lighter_dropdown(): void
    {
        $table = Livewire::test(ListVendors::class)->instance()->getTable();

        $this->assertLessThanOrEqual(2, count($table->getFilters()));
        $this->assertSame(FiltersLayout::Dropdown, $table->getFiltersLayout());
        $this->assertSame(1, $table->getFiltersFormColumns());
    }

    public function test_the_filters_trigger_is_a_labelled_button_everywhere(): void
    {
        foreach ([ListInventoryItems::class, ListVendors::class] as $page) {
            $action = Livewire::test($page)->instance()->getTable()->getFiltersTriggerAction();

            $this->assertSame('Filters', $action->getLabel(), $page . ' lost its filter button label');
        }
    }

    public function test_apply_and_reset_stay_pinned_to_the_dialog_footer(): void
    {
        // Filters are deferred, so nothing happens until Apply is pressed —
        // it must not be the control that scrolls out of reach.
        $action = Livewire::test(ListInventoryItems::class)->instance()->getTable()->getFiltersTriggerAction();

        $this->assertTrue($action->isModalFooterSticky());
        $this->assertTrue($action->isModalHeaderSticky());

        $footerLabels = array_map(
            fn ($footerAction) => $footerAction->getLabel(),
            $action->getVisibleModalFooterActions(),
        );

        $this->assertContains('Apply filters', $footerLabels);
        $this->assertContains('Reset', $footerLabels);
    }

    public function test_the_threshold_is_measured_on_filters_the_viewer_can_see(): void
    {
        // A filter hidden from this user takes up no room in the panel they
        // are about to open, so it must not push the layout into a dialog.
        $table = Livewire::test(ListInventoryItems::class)->instance()->getTable();

        $this->assertCount(
            count($table->getFilters()),
            array_filter($table->getFilters(true), fn ($filter) => $filter->isVisible()),
        );
    }

    public function test_the_presentation_is_a_default_a_table_can_override(): void
    {
        $table = Livewire::test(ListVendors::class)->instance()->getTable();

        TableFilterPresentation::apply($table);
        $table->filtersLayout(FiltersLayout::AboveContent);

        $this->assertSame(FiltersLayout::AboveContent, $table->getFiltersLayout());
    }
}
