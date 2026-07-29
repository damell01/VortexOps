<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DetectStreamersBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $this->adminUser->assignRole('admin');
        $this->channel = WhatnotChannel::create(['name' => 'Chan A', 'status' => 'active']);
    }

    private function show(string $title): Show
    {
        return Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => $title,
            'show_date'          => '2026-06-15',
            'import_source'      => 'auto_whatnot',
            'status'             => 'draft',
            'created_by'         => $this->adminUser->id,
        ]);
    }

    public function test_bulk_action_attaches_matching_streamers_and_skips_already_mapped_shows(): void
    {
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);
        $otherStreamer = Streamer::create(['name' => 'Diamo', 'status' => 'active', 'include_tips' => false]);

        $unmapped        = $this->show('Josh Break Night');
        $noMatch         = $this->show('Completely Unrelated Title');
        $alreadyMapped   = $this->show('Josh Break Night');
        $alreadyMapped->streamers()->attach($otherStreamer->id, ['is_primary' => true]);

        Livewire::actingAs($this->adminUser);

        Livewire::test(ListShows::class)
            ->set('activeTab', 'all')
            ->callTableBulkAction('detect_streamers', [
                $unmapped->getKey(),
                $noMatch->getKey(),
                $alreadyMapped->getKey(),
            ]);

        $this->assertCount(1, $unmapped->fresh()->streamers);
        $this->assertEquals('Josh', $unmapped->fresh()->streamers->first()->name);

        $this->assertCount(0, $noMatch->fresh()->streamers);

        // Already-mapped show is left untouched, not reassigned to a
        // higher-confidence match.
        $this->assertCount(1, $alreadyMapped->fresh()->streamers);
        $this->assertEquals('Diamo', $alreadyMapped->fresh()->streamers->first()->name);
    }

    public function test_header_action_detects_streamers_across_all_unmapped_shows_without_selection(): void
    {
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);
        $otherStreamer = Streamer::create(['name' => 'Diamo', 'status' => 'active', 'include_tips' => false]);

        $unmapped      = $this->show('Josh Break Night');
        $noMatch       = $this->show('Completely Unrelated Title');
        $alreadyMapped = $this->show('Josh Break Night');
        $alreadyMapped->streamers()->attach($otherStreamer->id, ['is_primary' => true]);

        Livewire::actingAs($this->adminUser);

        Livewire::test(ListShows::class)
            ->callAction('detect_streamers_all');

        $this->assertCount(1, $unmapped->fresh()->streamers);
        $this->assertEquals('Josh', $unmapped->fresh()->streamers->first()->name);

        $this->assertCount(0, $noMatch->fresh()->streamers);

        // Already-mapped show is untouched — the header action only targets
        // shows with zero streamers attached.
        $this->assertCount(1, $alreadyMapped->fresh()->streamers);
        $this->assertEquals('Diamo', $alreadyMapped->fresh()->streamers->first()->name);
    }

    public function test_header_action_is_not_visible_to_non_admins(): void
    {
        $streamerRole = Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $nonAdmin = User::factory()->create(['email' => 'streamer-user@test.com']);
        $nonAdmin->assignRole($streamerRole);

        Livewire::actingAs($nonAdmin);

        Livewire::test(ListShows::class)
            ->assertActionHidden('detect_streamers_all');
    }
}
