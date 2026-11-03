<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\OwnerTools;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerToolsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_sees_the_tool_cards(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email', 'dbellcreations@gmail.com')]);
        $this->actingAs($owner);

        $this->get(OwnerTools::getUrl())
            ->assertOk()
            ->assertSee('Owner Tools')
            ->assertSee('Whatnot API Control Center')
            ->assertSee('System Settings');
    }

    public function test_anyone_else_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']));

        $this->get(OwnerTools::getUrl())->assertForbidden();
    }
}
