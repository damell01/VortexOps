<?php

namespace Tests\Feature;

use App\Filament\Pages\AppSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\TeamMessagingSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamMessagingSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The admin-only pilot production starts in.
        config([
            'app.owner_email' => 'dbellcreations@gmail.com',
            'chatify-preview.available' => true,
            'chatify-preview.privileged_preview_enabled' => true,
            'chatify-preview.enabled' => true,
            'chatify-preview.team_enabled' => false,
            'chatify-preview.message_notifications_enabled' => false,
        ]);
    }

    public function test_never_set_leaves_the_env_in_charge(): void
    {
        TeamMessagingSwitch::apply();

        $this->assertNull(TeamMessagingSwitch::enabled());
        $this->assertFalse(config('chatify-preview.team_enabled'));
        $this->assertTrue(config('chatify-preview.privileged_preview_enabled'));
    }

    public function test_on_opens_messaging_to_the_team_without_alerts(): void
    {
        Setting::set(TeamMessagingSwitch::ENABLED, '1');

        TeamMessagingSwitch::apply();

        $this->assertTrue(config('chatify-preview.available'));
        $this->assertTrue(config('chatify-preview.enabled'));
        $this->assertTrue(config('chatify-preview.team_enabled'));
        $this->assertFalse(config('chatify-preview.privileged_preview_enabled'));
        $this->assertFalse(config('chatify-preview.message_notifications_enabled'));
    }

    public function test_alerts_follow_their_own_switch(): void
    {
        Setting::set(TeamMessagingSwitch::ENABLED, '1');
        Setting::set(TeamMessagingSwitch::ALERTS, '1');

        TeamMessagingSwitch::apply();

        $this->assertTrue(config('chatify-preview.message_notifications_enabled'));
    }

    public function test_off_returns_to_the_admin_pilot(): void
    {
        config(['chatify-preview.team_enabled' => true, 'chatify-preview.message_notifications_enabled' => true]);
        Setting::set(TeamMessagingSwitch::ENABLED, '0');

        TeamMessagingSwitch::apply();

        $this->assertFalse(config('chatify-preview.team_enabled'));
        $this->assertFalse(config('chatify-preview.message_notifications_enabled'));
        $this->assertTrue(config('chatify-preview.privileged_preview_enabled'));
    }

    public function test_only_the_owner_can_flip_it(): void
    {
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        Livewire::actingAs($owner)->test(AppSettings::class)->call('setTeamMessaging', true);
        $this->assertTrue(TeamMessagingSwitch::enabled());

        Livewire::actingAs($owner)->test(AppSettings::class)->call('setTeamMessaging', false);
        $this->assertFalse(TeamMessagingSwitch::enabled());
        $this->assertFalse(TeamMessagingSwitch::alerts(), 'turning messaging off also turns alerts off');
    }

    public function test_someone_else_cannot_flip_it(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));

        Livewire::actingAs($admin)->test(AppSettings::class)->call('setTeamMessaging', true)->assertForbidden();

        $this->assertNull(TeamMessagingSwitch::enabled());
    }
}
