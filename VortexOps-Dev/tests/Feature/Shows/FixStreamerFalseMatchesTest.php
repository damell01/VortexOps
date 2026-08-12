<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixStreamerFalseMatchesTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);
    }

    private function show(string $title): Show
    {
        return Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => $title,
            'show_date'          => now()->toDateString(),
            'status'             => 'draft',
        ]);
    }

    public function test_dry_run_reports_but_does_not_detach(): void
    {
        $ty    = Streamer::create(['name' => 'Ty', 'status' => 'active', 'include_tips' => false]);
        $tyler = Streamer::create(['name' => 'Tyler', 'status' => 'active', 'include_tips' => false]);

        $show = $this->show('Tyler Break Night');
        $show->streamers()->attach($ty->id, ['is_primary' => false]);
        $show->streamers()->attach($tyler->id, ['is_primary' => true]);

        $this->artisan('whatnot:fix-streamer-false-matches')
            ->expectsTable(
                ['Show ID', 'Title', 'False match (removing)', 'Kept'],
                [[$show->id, 'Tyler Break Night', 'Ty', 'Tyler']]
            )
            ->assertExitCode(0);

        $this->assertCount(2, $show->fresh()->streamers, 'dry run must not modify anything');
    }

    public function test_apply_detaches_only_the_false_match(): void
    {
        $ty    = Streamer::create(['name' => 'Ty', 'status' => 'active', 'include_tips' => false]);
        $tyler = Streamer::create(['name' => 'Tyler', 'status' => 'active', 'include_tips' => false]);

        $show = $this->show('Tyler Break Night');
        $show->streamers()->attach($ty->id, ['is_primary' => false]);
        $show->streamers()->attach($tyler->id, ['is_primary' => true]);

        $this->artisan('whatnot:fix-streamer-false-matches', ['--apply' => true])
            ->assertExitCode(0);

        $show->refresh();
        $this->assertCount(1, $show->streamers);
        $this->assertEquals($tyler->id, $show->streamers->first()->id);
    }

    public function test_leaves_a_genuine_ty_and_luna_double_booking_alone(): void
    {
        $ty    = Streamer::create(['name' => 'Ty', 'status' => 'active', 'include_tips' => false]);
        $tyler = Streamer::create(['name' => 'Tyler', 'status' => 'active', 'include_tips' => false]);

        // "Ty" genuinely stands alone in this title too — not just a
        // substring of "Tyler" — so both are legitimate matches.
        $show = $this->show('Ty and Tyler Break Night');
        $show->streamers()->attach($ty->id, ['is_primary' => false]);
        $show->streamers()->attach($tyler->id, ['is_primary' => true]);

        $this->artisan('whatnot:fix-streamer-false-matches', ['--apply' => true])
            ->assertExitCode(0);

        $this->assertCount(2, $show->fresh()->streamers, 'a genuine double-booking must not be touched');
    }

    public function test_leaves_two_unrelated_streamers_on_the_same_show_alone(): void
    {
        $josh   = Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);
        $daniel = Streamer::create(['name' => 'Daniel', 'status' => 'active', 'include_tips' => false]);

        $show = $this->show('Josh and Daniel Break Night');
        $show->streamers()->attach($josh->id, ['is_primary' => true]);
        $show->streamers()->attach($daniel->id, ['is_primary' => false]);

        $this->artisan('whatnot:fix-streamer-false-matches', ['--apply' => true])
            ->assertExitCode(0);

        $this->assertCount(2, $show->fresh()->streamers, 'unrelated names are not substrings of each other — nothing to fix');
    }

    public function test_reports_nothing_when_no_false_matches_exist(): void
    {
        $this->artisan('whatnot:fix-streamer-false-matches')
            ->expectsOutputToContain('No false-match pairs found')
            ->assertExitCode(0);
    }
}
