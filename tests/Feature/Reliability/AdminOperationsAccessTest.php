<?php
namespace Tests\Feature\Reliability;

use App\Filament\Pages\AppSettings;
use App\Filament\Pages\ImportStatus;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\ShowIngestionLog;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOperationsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        NavVisibility::flushMemo();
        return $user;
    }

    public function test_fulfillment_admin_has_admin_access_and_selected_navigation(): void
    {
        $user = $this->admin('fulfillment_admin');
        NavVisibility::setVisibleForRole('admin', [ImportStatus::class]);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(AppSettings::canAccess());
        $this->assertFalse(NavVisibility::isHiddenForUser(AppSettings::class, $user));
        $this->assertFalse(ImportStatus::canAccess());
        $this->assertFalse(ImportStatus::shouldRegisterNavigation());
        $this->assertTrue(\App\Filament\Resources\UserResource::canAccess());
        $this->assertFalse(NavVisibility::isHiddenForUser(\App\Filament\Resources\UserResource::class, $user));
    }

    public function test_admin_can_save_business_settings_but_cannot_write_system_settings(): void
    {
        $this->admin();
        Setting::set('ai_provider', 'ollama');
        Setting::set('show_import_mode', 'manual');
        Livewire::test(AppSettings::class)->assertOk()
            ->assertSee('Receiving')->assertSee('Streamer Inventory Access')
            ->assertDontSee('Generate embeddings')->assertDontSee('Run Migrations')
            ->set('ai_provider', 'openai')->set('show_import_mode', 'auto_whatnot')
            ->set('shipping_surcharge_rate', '5.25')->call('saveSettings')->assertHasNoErrors();
        $this->assertSame('5.25', Setting::get('shipping_surcharge_rate'));
        $this->assertSame('ollama', Setting::get('ai_provider'));
        $this->assertSame('manual', Setting::get('show_import_mode'));
        Livewire::test(AppSettings::class)->call('runMigrations')->assertForbidden();
    }

    public function test_import_status_renders_real_channel_events_without_starting_a_scrape(): void
    {
        $this->actingAs(User::firstWhere('email', config('app.owner_email')) ?? User::factory()->create(['email' => config('app.owner_email')]));
        $this->assertTrue(ImportStatus::canAccess());
        $channel = WhatnotChannel::create(['name'=>'Cards','status'=>'active','include_in_import'=>true]);
        ShowIngestionLog::create(['whatnot_channel_id'=>$channel->id,'source'=>'whatnot_show_analytics','status'=>'success','raw_payload'=>['created'=>2,'updated'=>3]]);
        Livewire::test(ImportStatus::class)->assertOk()->assertSee('Cards')->assertSee('Last successful record update')->assertSee('No completed run recorded');
    }
}
