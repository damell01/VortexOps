<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The channel switcher sits in the custom top menu (desktop-mega-nav), which
 * replaced the sidebar on every signed-in page. The sidebar is no longer built
 * at all (App\Livewire\HiddenSidebar), so the switcher renders once per menu
 * (desktop and phone) — it used to render a third, invisible copy in the sidebar.
 */
class ChannelSwitcherPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');

        return $u;
    }

    public function test_channel_switcher_renders_once_in_the_top_menu_and_the_sidebar_is_not_built(): void
    {
        $this->actingAs($this->admin());

        $html = $this->get('/admin')->getContent();

        $menuPos = strpos($html, 'vx-desktop-mega-nav');
        $switcherPos = strpos($html, 'vx-channel-switcher');

        $this->assertNotFalse($menuPos, 'Top menu did not render.');
        $this->assertNotFalse($switcherPos, 'Channel switcher did not render at all.');
        $this->assertGreaterThan($menuPos, $switcherPos, 'The switcher belongs to the top menu.');
        // One for the desktop menu, one for the phone menu — no third, hidden copy.
        $this->assertSame(2, substr_count($html, 'class="vx-channel-switcher'), 'Desktop and phone menus only.');
        $this->assertStringNotContainsString('fi-sidebar-nav', $html, 'The hidden sidebar should not be built.');
    }

    public function test_channel_switcher_hidden_for_users_who_cannot_switch_channels(): void
    {
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $streamerUser = User::factory()->create();
        $streamerUser->assignRole('streamer');
        $this->actingAs($streamerUser);

        $html = $this->get('/admin')->getContent();

        $this->assertStringNotContainsString('vx-channel-switcher', $html);
    }
}
