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

    public function test_admin_can_read_both_pdfs_and_replay_help(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        NavVisibility::flushMemo();
        Livewire::test(HelpCenter::class)->assertOk()->assertSee('PDF guides')->assertSee('Start walkthrough');
        $this->get('/admin/guides/admin')->assertOk()->assertHeader('content-type', 'application/pdf');
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
        $this->get('/admin/guides/streamer')->assertOk();
    }

    public function test_pdf_routes_require_login(): void
    {
        $this->get('/admin/guides/admin')->assertRedirect();
    }
}
