<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** When an imported show is retitled, the streamer detection made is worked out again. */
class RetitledShowRedetectsStreamerTest extends TestCase
{
    use RefreshDatabase;

    private Streamer $cate;
    private Streamer $dennis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cate = Streamer::create(['name' => 'Cate', 'status' => 'active']);
        $this->dennis = Streamer::create(['name' => 'Dennis', 'status' => 'active']);
    }

    private function show(string $title): Show
    {
        return Show::create(['title' => $title, 'show_date' => today()->addDay(), 'status' => 'draft', 'import_source' => 'auto_whatnot']);
    }

    private function streamerIds(Show $show): array
    {
        return $show->fresh()->streamers()->pluck('streamers.id')->all();
    }

    public function test_an_auto_detected_streamer_follows_the_new_title(): void
    {
        $show = $this->show('FREE PACKS w/ Cate');
        $this->assertSame([$this->cate->id], $this->streamerIds($show));

        $show->update(['title' => 'FREE PACKS w/ Dennis']);

        $this->assertSame([$this->dennis->id], $this->streamerIds($show));
    }

    public function test_the_empty_report_stub_moves_to_the_new_streamer(): void
    {
        $show = $this->show('FREE PACKS w/ Cate');
        $log = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->cate->id, 'status' => 'pending']);

        $show->update(['title' => 'FREE PACKS w/ Dennis']);

        $this->assertSame($this->dennis->id, $log->fresh()->streamer_id);
    }

    public function test_a_title_naming_nobody_sends_the_show_back_to_unassigned(): void
    {
        $show = $this->show('FREE PACKS w/ Cate');

        $show->update(['title' => 'FREE PACKS all night']);

        $this->assertSame([], $this->streamerIds($show));
    }

    public function test_a_streamer_picked_by_hand_stays(): void
    {
        $show = $this->show('FREE PACKS w/ Cate');
        $show->streamers()->sync([$this->dennis->id => ['is_primary' => true]]); // admin's choice

        $show->update(['title' => 'FREE PACKS tonight w/ Cate']);

        $this->assertSame([$this->dennis->id], $this->streamerIds($show));
    }

    public function test_a_submitted_report_keeps_its_streamer(): void
    {
        $show = $this->show('FREE PACKS w/ Cate');
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->cate->id, 'status' => 'streamer_reviewed']);

        $show->update(['title' => 'FREE PACKS w/ Dennis']);

        $this->assertSame([$this->cate->id], $this->streamerIds($show));
    }
}
