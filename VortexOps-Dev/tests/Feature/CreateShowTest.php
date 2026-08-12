<?php
namespace Tests\Feature;

use App\Filament\Resources\ShowResource\Pages\CreateShow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_create_show_page_renders(): void
    {
        $user = User::factory()->create(['email' => 'admin@test.com']);
        $user->assignRole('admin');
        
        Livewire::actingAs($user)
            ->test(CreateShow::class)
            ->assertOk();
    }

    public function test_create_show_manual_submission(): void
    {
        $user = User::factory()->create(['email' => 'admin@test.com']);
        $user->assignRole('admin');
        
        Livewire::actingAs($user)
            ->test(CreateShow::class)
            ->set('data.show_date', now()->toDateString())
            ->set('data.title', 'Test Show #1')
            ->set('data.gross_revenue', '100')
            ->call('create')
            ->assertHasNoErrors();
        
        $this->assertDatabaseHas('shows', ['title' => 'Test Show #1']);
    }
}
