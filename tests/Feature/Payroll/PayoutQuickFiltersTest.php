<?php
namespace Tests\Feature\Payroll;
use App\Filament\Resources\PayoutResource\Pages\ListPayouts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
/** The Payouts list's quick-filter chips each render and narrow the list. */
class PayoutQuickFiltersTest extends TestCase
{
    use RefreshDatabase;
    public function test_every_quick_filter_opens(): void
    {
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['streams', 'payouts', 'reporting']));
        \App\Support\AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        foreach (['all', 'review', 'pay', 'paid', 'week'] as $t) {
            Livewire::test(ListPayouts::class)->set('activeTab', $t)->assertOk();
        }
        Livewire::test(ListPayouts::class)->set('tableRecordsPerPage', 50)->assertOk();
        $page = Livewire::test(ListPayouts::class)->set('activeTab', 'pay')->assertSee('To review');
        $this->assertSame('Nothing in this list', $page->instance()->getTable()->getEmptyStateHeading());
        $page->set('activeTab', 'all');
        $this->assertSame('No payouts yet', $page->instance()->getTable()->getEmptyStateHeading());
    }
}
