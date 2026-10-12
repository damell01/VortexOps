<?php
namespace Tests\Feature\Reliability;

use App\Filament\Pages\HelpCenter;
use App\Models\User;
use App\Support\GuidedHelp;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuidedHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_pdfs_inside_help_without_walkthrough_cards(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        NavVisibility::flushMemo();
        Livewire::test(HelpCenter::class)->assertOk()->assertSee('PDF guides')->assertDontSee('Start walkthrough')->assertDontSee('Interactive walkthroughs');
        $response = $this->get('/admin/guides/admin')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(1366657, $response->baseResponse->getFile()->getSize());
        $this->get('/admin/guides/streamer?download=1')->assertOk();
    }

    public function test_streamer_cannot_read_admin_pdf_or_admin_tour(): void
    {
        Role::findOrCreate('streamer', 'web');
        $user = User::factory()->create();
        $user->assignRole('streamer');
        $this->actingAs($user);
        NavVisibility::flushMemo();
        $this->assertArrayHasKey('streamer', GuidedHelp::documents());
        $this->assertArrayNotHasKey('admin', GuidedHelp::documents());
        $this->assertArrayNotHasKey('users', GuidedHelp::tours());
        $this->get('/admin/guides/admin')->assertForbidden();
        $this->get('/admin/guides/../../.env')->assertNotFound();
        $response = $this->get('/admin/guides/streamer')->assertOk();
        $this->assertSame(706484, $response->baseResponse->getFile()->getSize());
    }

    public function test_inventory_pdf_and_reader_follow_inventory_access(): void
    {
        $this->enableAdminModules();
        Role::findOrCreate('admin','web');
        $admin=User::factory()->create(); $admin->assignRole('admin'); $this->actingAs($admin);
        NavVisibility::flushMemo();
        Livewire::test(HelpCenter::class)->call('selectGuide','inventory')
            ->assertSet('guide','inventory')->assertSee('Inventory receiving')->assertSee('iframe',false);
        $this->get('/admin/guides/inventory')->assertOk()->assertHeader('content-type','application/pdf');
        $this->get('/admin/guides/inventory?download=1')->assertOk()->assertHeader('content-disposition','attachment; filename=inventory-guide.pdf');
        Role::findOrCreate('streamer','web');
        $streamer=User::factory()->create(); $streamer->assignRole('streamer'); $this->actingAs($streamer);
        NavVisibility::flushMemo();
        $this->assertArrayNotHasKey('inventory',GuidedHelp::documents());
        $this->get('/admin/guides/inventory')->assertForbidden();
        Livewire::test(HelpCenter::class,['guide'=>'admin'])->assertDontSee('Admin, users &amp; payroll',false);
    }

    public function test_pdf_routes_require_login(): void
    {
        $this->get('/admin/guides/admin')->assertRedirect();
        $this->get('/admin/guides/inventory')->assertRedirect();
    }
}
