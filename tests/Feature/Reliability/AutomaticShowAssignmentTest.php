<?php

namespace Tests\Feature\Reliability;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutomaticShowAssignmentTest extends TestCase
{
    use RefreshDatabase;
    private function show(string $title): Show
    {
        return Show::withoutEvents(fn () => Show::create(['title' => $title, 'show_date' => today(), 'status' => 'draft']));
    }

    public function test_command_creates_accounts_aliases_and_assigns_groups_without_ui_and_is_repeatable(): void
    {
        $a = $this->show('Boxes w/Niko🍀'); $b = $this->show('Sale WITH NIKO🔥'); $unclear = $this->show('Friday boxes'); $multiple = $this->show('Boxes w/Ty & Luna');
        $this->artisan('shows:auto-assign-streamers')->assertSuccessful();
        $profile = Streamer::sole(); $user = User::where('email', 'niko@vortexops.tech')->sole();
        $this->assertSame($profile->id, $a->streamers()->sole()->id);
        $this->assertSame($profile->id, $b->streamers()->sole()->id);
        $this->assertSame(1, $profile->aliases()->count());
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('password123!', $user->password));
        $this->assertTrue($user->hasRole('streamer'));
        $this->assertSame(0, $unclear->streamers()->count());
        $this->assertSame(0, $multiple->streamers()->count());
        $password = $user->password;
        $this->artisan('shows:auto-assign-streamers')->assertSuccessful();
        $this->assertSame(1, Streamer::count());
        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_saved_alias_reuses_profile_and_account_without_resetting_password(): void
    {
        $user = User::factory()->create(['password' => 'PersonalPassword456!']);
        $profile = Streamer::create(['name' => 'Nicholas', 'status' => 'active', 'user_id' => $user->id]);
        $profile->aliases()->create(['alias' => 'Niko', 'source' => 'manual']);
        $show = $this->show('Boxes w/Niko');
        $this->artisan('shows:auto-assign-streamers')->assertSuccessful();
        $this->assertSame($profile->id, $show->streamers()->sole()->id);
        $this->assertSame(1, Streamer::count());
        $this->assertTrue(Hash::check('PersonalPassword456!', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_ambiguous_existing_aliases_stay_unassigned(): void
    {
        foreach (['Nicholas', 'Nikolai'] as $name) Streamer::create(['name' => $name, 'status' => 'active'])->aliases()->create(['alias' => 'Niko']);
        $show = $this->show('Boxes w/Niko');
        $this->artisan('shows:auto-assign-streamers')->assertSuccessful();
        $this->assertSame(0, $show->streamers()->count());
        $this->assertSame(0, User::count());
    }
    public function test_already_assigned_profile_also_receives_missing_login(): void
    {
        $profile = Streamer::create(['name' => 'Luna', 'status' => 'active']);
        $show = $this->show('Boxes w/Luna');
        $show->streamers()->attach($profile->id, ['is_primary' => true]);
        $this->artisan('shows:auto-assign-streamers')->assertSuccessful();
        $user = User::where('email', 'luna@vortexops.tech')->sole();
        $this->assertSame($user->id, $profile->fresh()->user_id);
        $this->assertTrue($user->must_change_password);
        $this->assertSame($profile->id, $show->streamers()->sole()->id);
    }

    public function test_confirmed_ashenway_and_b_dollar_hosts_are_detected_without_markers(): void
    {
        $ashen = $this->show('ASHENWAY $1 STARTS 🔥');
        $b = $this->show('B$ FREE BOXES 🔥');
        $notHost = $this->show('Ashenways collectibles');
        $this->artisan('shows:auto-assign-streamers')->assertSuccessful();
        $this->assertSame('Ashenway', $ashen->streamers()->sole()->name);
        $this->assertSame('B, $b->streamers()->sole()->name);
        $this->assertSame('B, $b->streamers()->sole()->aliases()->sole()->alias);
        $this->assertNotNull(User::where('email', 'ashenway@vortexops.tech')->first());
        $this->assertNotNull(User::where('email', 'b@vortexops.tech')->first());
        $this->assertSame(0, $notHost->streamers()->count());
    }
}
