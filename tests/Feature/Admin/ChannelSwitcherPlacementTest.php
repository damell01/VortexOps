<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The channel switcher used to live in the topbar as a bare native <select>.
 * Moved into the sidebar (rendered via SIDEBAR_NAV_START, right above the nav
 * groups) so it reads as part of the app's primary navigation rather than a
 * floating topbar control.
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

    public function test_channel_switcher_renders_inside_the_sidebar_nav_not_the_topbar(): void
    {
        $this->actingAs($this->admin());

        $html = $this->get('/admin')->getContent();

        $navPos = strpos($html, 'fi-sidebar-nav');
        $topbarClosePos = strpos($html, 'fi-topbar-close-sidebar-btn') ?: strpos($html, '</header>');
        $switcherPos = strpos($html, 'vx-channel-switcher');

        $this->assertNotFalse($switcherPos, 'Channel switcher did not render at all.');
        $this->assertNotFalse($navPos, 'Sidebar nav did not render.');
        // The switcher must appear after the sidebar nav opens (SIDEBAR_NAV_START),
        // not before it inside the topbar/header markup that precedes the sidebar.
        $this->assertGreaterThan($navPos, $switcherPos);
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
