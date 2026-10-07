<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AppSettings;
use App\Filament\Pages\NotificationSettings;
use App\Models\InventoryLocation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One deleted user must not take the whole settings page down.
 *
 * Notification recipients are validated with exists:users,id on every save,
 * and their checkboxes only render while that notification's mode is
 * "custom". So a list left behind by a mode that has since changed — or a
 * user deleted after being picked — was an id with no checkbox to untick,
 * failing validation forever. The message named notify_show_ready_users.0,
 * a field with nothing on screen, and every unrelated setting on the page
 * went down with it: the reported symptom was that the default receiving
 * location would not save.
 */
class SettingsSurviveADeletedRecipientTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->location = InventoryLocation::create(['name' => 'Main Warehouse', 'is_active' => true]);
    }

    private function strandRecipients(): void
    {
        $doomed = User::factory()->create(['email' => 'leaver@example.com']);

        Setting::set('notify_show_ready_users', json_encode([$doomed->id, $this->owner->id]));
        Setting::set('notify_show_reconciled_users', json_encode([$doomed->id]));
        Setting::set('notify_show_ready_mode', 'admins');
        Setting::set('notify_show_reconciled_mode', 'admins');

        $doomed->delete();
    }

    public function test_a_deleted_recipient_is_dropped_on_load(): void
    {
        $this->strandRecipients();
        Setting::set('notify_show_ready_mode', 'custom');
        Setting::set('notify_show_reconciled_mode', 'custom');

        $page = Livewire::actingAs($this->owner)->test(NotificationSettings::class);

        $this->assertSame([(string) $this->owner->id], $page->instance()->rules['show_ready']['users']);
        $this->assertSame([], $page->instance()->rules['show_reconciled']['users']);
    }

    public function test_the_receiving_location_saves_despite_a_stranded_recipient(): void
    {
        $this->strandRecipients();

        Livewire::actingAs($this->owner)
            ->test(AppSettings::class)
            ->set('default_receiving_location_id', $this->location->id)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame((string) $this->location->id, (string) Setting::get('default_receiving_location_id'));
    }

    public function test_a_stale_recipient_cannot_block_saving_notification_settings(): void
    {
        // The same failure arriving from a stale browser tab rather than from
        // storage: an id with no person behind it is dropped, not validated.
        Livewire::actingAs($this->owner)
            ->test(NotificationSettings::class)
            ->set('rules.show_ready.users', ['999999', (string) $this->owner->id])
            ->call('save')
            ->assertHasNoErrors();

        $saved = json_decode(Setting::get('notification_rules'), true);
        $this->assertSame([$this->owner->id], $saved['show_ready']['users']);
    }
}
