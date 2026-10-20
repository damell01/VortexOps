<?php

namespace Tests\Feature\Shows;

use App\Livewire\StreamerHome;
use App\Models\Streamer;
use App\Models\User;
use App\Support\Buddy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The dashboard buddy: asked once, saved to the account, shown in the banner. */
class DashboardBuddyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('streamer');
        Streamer::create(['name' => 'Buddy Tester', 'status' => 'active', 'user_id' => $this->user->id]);
        $this->actingAs($this->user->fresh());
    }

    public function test_a_streamer_who_has_not_chosen_is_asked_and_their_choice_is_saved(): void
    {
        Livewire::test(StreamerHome::class)
            ->assertSee('Choose your dashboard buddy')
            ->call('chooseBuddy', 'gengar')
            ->assertDontSee('Choose your dashboard buddy')
            ->assertSeeHtml('/img/pokemon3d/gengar.gif');

        $this->assertSame('gengar', $this->user->fresh()->buddy);
    }

    public function test_skipping_stops_asking_for_the_rest_of_the_session(): void
    {
        Livewire::test(StreamerHome::class)->call('skipBuddy')->assertDontSee('Choose your dashboard buddy');
        Livewire::test(StreamerHome::class)->assertDontSee('Choose your dashboard buddy');

        $this->assertNull($this->user->fresh()->buddy);
    }

    public function test_someone_who_has_chosen_is_not_asked(): void
    {
        $this->user->forceFill(['buddy' => 'snorlax'])->save();
        $this->actingAs($this->user->fresh());

        Livewire::test(StreamerHome::class)->assertDontSee('Choose your dashboard buddy')->assertSeeHtml('/img/pokemon3d/snorlax.gif');
    }

    public function test_none_hides_the_buddy_and_random_is_steady_for_a_day(): void
    {
        $this->user->forceFill(['buddy' => 'none'])->save();
        $this->assertNull(Buddy::visitor($this->user->fresh()));

        $this->user->forceFill(['buddy' => 'random'])->save();
        $day = \Carbon\CarbonImmutable::parse('2026-10-15');
        $pick = Buddy::visitor($this->user->fresh(), $day);
        $this->assertContains($pick, Buddy::CHOICES);
        $this->assertSame($pick, Buddy::visitor($this->user->fresh(), $day->setTime(23, 0)));
    }

    public function test_an_unknown_choice_is_ignored(): void
    {
        Livewire::test(StreamerHome::class)->call('chooseBuddy', 'missingno');

        $this->assertNull($this->user->fresh()->buddy);
    }

    public function test_every_choice_has_a_sprite(): void
    {
        foreach (Buddy::CHOICES as $buddy) {
            $this->assertFileExists(public_path("img/pokemon3d/{$buddy}.gif"));
        }
    }

    public function test_the_profile_page_changes_the_buddy(): void
    {
        Livewire::test(\App\Filament\Auth\EditProfile::class)
            ->assertFormFieldExists('buddy')
            ->fillForm(['buddy' => 'random'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('random', $this->user->fresh()->buddy);
    }
}
