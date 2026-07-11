<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\RecentShowsWidget;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A streamer must only see shows they were on — including shows co-hosted with
 * other streamers — while admins/owner see everything.
 */
class StreamerDashboardScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /** @return array{0:User,1:Show,2:Show,3:Show} [streamerUser, mine, theirs, shared] */
    private function scenario(): array
    {
        $mine   = Streamer::create(['name' => 'Mine', 'status' => 'active']);
        $theirs = Streamer::create(['name' => 'Theirs', 'status' => 'active']);

        $user = User::factory()->create(['email' => 'streamer@test.com']);
        $user->assignRole('streamer');
        $mine->update(['user_id' => $user->id]); // link via streamers.user_id (hasOne)

        $showMine   = Show::create(['title' => 'Mine Show', 'show_date' => '2026-06-01', 'status' => 'draft', 'created_by' => $user->id]);
        $showTheirs = Show::create(['title' => 'Theirs Show', 'show_date' => '2026-06-02', 'status' => 'draft', 'created_by' => $user->id]);
        $showShared = Show::create(['title' => 'Shared Show', 'show_date' => '2026-06-03', 'status' => 'draft', 'created_by' => $user->id]);

        $showMine->streamers()->attach($mine->id);
        $showTheirs->streamers()->attach($theirs->id);
        $showShared->streamers()->attach([$mine->id, $theirs->id]);

        return [$user, $showMine, $showTheirs, $showShared];
    }

    public function test_streamer_sees_only_their_own_and_cohosted_shows(): void
    {
        [$user] = $this->scenario();
        Livewire::actingAs($user);

        Livewire::test(RecentShowsWidget::class)
            ->loadTable()
            ->assertSee('Mine Show')
            ->assertSee('Shared Show')
            ->assertDontSee('Theirs Show');
    }

    public function test_streamer_with_no_linked_profile_sees_no_shows(): void
    {
        $this->scenario();
        $orphan = User::factory()->create(['email' => 'orphan@test.com']);
        $orphan->assignRole('streamer'); // streamer role but no Streamer record

        Livewire::actingAs($orphan);

        Livewire::test(RecentShowsWidget::class)
            ->loadTable()
            ->assertDontSee('Mine Show')
            ->assertDontSee('Shared Show')
            ->assertDontSee('Theirs Show');
    }

    public function test_admin_sees_all_shows(): void
    {
        $this->scenario();
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');

        Livewire::actingAs($admin);

        Livewire::test(RecentShowsWidget::class)
            ->loadTable()
            ->assertSee('Mine Show')
            ->assertSee('Theirs Show')
            ->assertSee('Shared Show');
    }
}
