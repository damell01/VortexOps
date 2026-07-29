<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FixStreamerRoleCasingMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase already runs this migration once during initial setup
     * (when there's nothing to fix), so re-triggering it mid-test needs to
     * bypass Laravel's "already ran" tracking — load the file directly and
     * call up() on it rather than going through `artisan migrate`.
     */
    private function runMigrationUp(): void
    {
        $migration = require base_path('database/migrations/2026_07_29_140000_fix_streamer_role_name_casing.php');
        $migration->up();
    }

    public function test_renames_capitalized_streamer_role_and_migrates_page_access_settings(): void
    {
        $role = Role::create(['name' => 'Streamer', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Streamer');

        Setting::set('role_hidden_nav', json_encode([
            'Streamer' => ['App\\Filament\\Resources\\SomeResource'],
            'admin'    => ['App\\Filament\\Pages\\SystemHealth'],
        ]));
        Setting::set('role_readonly_nav', json_encode([
            'Streamer' => ['App\\Filament\\Resources\\OtherResource'],
        ]));

        $this->runMigrationUp();

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'streamer']);
        $this->assertTrue($user->fresh()->hasRole('streamer'), 'user should now match the lowercase role check');

        $hidden = json_decode(Setting::get('role_hidden_nav'), true);
        $this->assertArrayNotHasKey('Streamer', $hidden);
        $this->assertEquals(['App\\Filament\\Resources\\SomeResource'], $hidden['streamer']);
        $this->assertEquals(['App\\Filament\\Pages\\SystemHealth'], $hidden['admin']);

        $readonly = json_decode(Setting::get('role_readonly_nav'), true);
        $this->assertArrayNotHasKey('Streamer', $readonly);
        $this->assertEquals(['App\\Filament\\Resources\\OtherResource'], $readonly['streamer']);
    }

    public function test_is_a_no_op_when_no_capitalized_role_exists(): void
    {
        Role::where('name', 'streamer')->delete();
        Role::create(['name' => 'streamer', 'guard_name' => 'web']);

        $this->runMigrationUp();

        $this->assertDatabaseHas('roles', ['name' => 'streamer']);
        $this->assertDatabaseMissing('roles', ['name' => 'Streamer']);
    }

    public function test_refuses_to_run_when_both_cased_roles_already_exist(): void
    {
        Role::create(['name' => 'Streamer', 'guard_name' => 'web']);
        Role::create(['name' => 'streamer', 'guard_name' => 'web']);

        $this->expectException(\RuntimeException::class);

        $this->runMigrationUp();
    }
}
