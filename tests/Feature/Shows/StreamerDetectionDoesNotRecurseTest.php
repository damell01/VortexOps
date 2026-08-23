<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Importing a show whose title names a streamer must terminate.
 *
 * It did not. Detection runs from the show observer, and the observer re-runs
 * detection on update — so writing the suggestion with update() called back
 * into detection, which wrote again, and the attach that would have ended it
 * was never reached, because each nested pass looked and found no streamer
 * attached yet.
 *
 * The failure had no error to read. The import hung: not slow, never
 * returning, holding the shared browser lock the whole time, so every
 * scheduled Whatnot job behind it queued on a lock nothing would release. In
 * the test suite it looked like a suite that was still running, for
 * forty-five minutes.
 *
 * A matching title is the ordinary case for this business — every show is
 * named after whoever is on camera.
 */
class StreamerDetectionDoesNotRecurseTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
    }

    private function importedShow(string $title): Show
    {
        return Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => $title,
            'show_date'          => '2026-06-15',
            'import_source'      => 'auto_whatnot',
            'status'             => 'draft',
            'created_by'         => $this->user->id,
        ]);
    }

    public function test_a_show_named_after_a_streamer_imports(): void
    {
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);

        $show = $this->importedShow('Josh Break Night');

        $this->assertSame('Josh', $show->fresh()->streamers->first()?->name);
    }

    public function test_the_streamer_is_attached_exactly_once(): void
    {
        // The loop attached on every pass it survived, so a run that returned
        // at all would have left duplicate pivot rows behind it.
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);

        $show = $this->importedShow('Josh Break Night');

        $this->assertSame(
            1,
            DB::table('show_streamer')->where('show_id', $show->id)->count(),
        );
    }

    public function test_a_title_matching_two_streamers_still_terminates(): void
    {
        // More matches means more attaches per pass, which is the shape most
        // likely to survive a naive fix that only counted iterations.
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);
        Streamer::create(['name' => 'Connor', 'status' => 'active', 'include_tips' => false]);

        $show = $this->importedShow('Josh and Connor Rip Night');

        $this->assertSame(2, $show->fresh()->streamers->count());
    }

    public function test_a_show_matching_nothing_imports(): void
    {
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);

        $show = $this->importedShow('Completely Unrelated Title');

        $this->assertSame(0, $show->fresh()->streamers->count());
    }

    public function test_renaming_a_show_onto_a_streamer_name_terminates(): void
    {
        // The other way in: the observer re-runs detection when the title
        // changes, so an edit reaches the same code by a different door.
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);

        $show = $this->importedShow('Completely Unrelated Title');
        $show->update(['title' => 'Josh Break Night']);

        $this->assertSame('Josh', $show->fresh()->streamers->first()?->name);
    }

    public function test_the_suggestion_is_recorded(): void
    {
        // Going quiet must not mean going missing: the suggestion is what the
        // admin screens read to explain why a streamer was attached.
        Streamer::create(['name' => 'Josh', 'status' => 'active', 'include_tips' => false]);

        $show = $this->importedShow('Josh Break Night');

        $suggestion = $show->fresh()->ai_streamer_suggestion;

        $this->assertIsArray($suggestion);
        $this->assertSame('Josh', $suggestion[0]['streamer_name'] ?? null);
    }
}
