<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The analytics walk needs one livestream UUID, and it was reading it off a
 * page it does not otherwise need.
 *
 * /account/analytics?tab=livestream&live_id=… is the surface that produced the
 * revenue figures, and it steps back through a channel's history on its own
 * once it has somewhere to start. The start was scraped off /dashboard/lives —
 * so when that list stopped being served, a scrape that had nothing wrong with
 * it stopped too.
 *
 * Every show already imported carries a UUID in its detail_url, and so does
 * every row the last run logged and failed to keep. Reading the seed from
 * there means the list page has to work once, ever.
 */
class WhatnotAnalyticsSeedTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = 'a0a97cbb-097e-4c1f-9174-98ad66937e14';

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
    }

    private function scraper(): WhatnotScraper
    {
        return app(WhatnotScraper::class);
    }

    public function test_the_seed_comes_from_an_already_imported_show(): void
    {
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'          => '2026-08-20',
            'detail_url'         => 'https://www.whatnot.com/dashboard/live/' . self::UUID,
            'created_by'         => 1,
        ]);

        $this->assertSame(self::UUID, $this->scraper()->seedLiveIdFor($this->channel));
    }

    public function test_the_newest_show_wins(): void
    {
        // Walking back from the newest show reaches the whole history; starting
        // from an old one reaches only what came before it.
        $older = '11111111-1111-1111-1111-111111111111';

        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Old show',
            'show_date'          => '2024-01-01',
            'detail_url'         => 'https://www.whatnot.com/dashboard/live/' . $older,
            'created_by'         => 1,
        ]);

        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Recent show',
            'show_date'          => '2026-08-20',
            'detail_url'         => 'https://www.whatnot.com/dashboard/live/' . self::UUID,
            'created_by'         => 1,
        ]);

        $this->assertSame(self::UUID, $this->scraper()->seedLiveIdFor($this->channel));
    }

    public function test_a_failed_import_still_leaves_a_usable_seed(): void
    {
        // The case that actually applies right now: nothing imported, but the
        // last run logged four rows it could not keep, each carrying a UUID.
        ShowIngestionLog::create([
            'source'        => 'whatnot',
            'status'        => 'failed',
            'error_message' => 'Scraped row had a title but no show_date (required) — show was not created.',
            'raw_payload'   => [
                'title'      => 'TGIF RIPS AND SINGLES w/Connor',
                'detail_url' => 'https://www.whatnot.com/dashboard/live/' . self::UUID,
            ],
        ]);

        $this->assertSame(self::UUID, $this->scraper()->seedLiveIdFor($this->channel));
    }

    public function test_a_show_without_a_uuid_is_not_offered_as_a_seed(): void
    {
        // A detail_url that carries no UUID would send the walk to a page that
        // resolves to nothing, which is indistinguishable from a channel with
        // no history.
        Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Manually added show',
            'show_date'          => '2026-08-20',
            'detail_url'         => 'https://www.whatnot.com/live/some-slug',
            'created_by'         => 1,
        ]);

        $this->assertNull($this->scraper()->seedLiveIdFor($this->channel));
    }

    public function test_another_channels_history_is_not_borrowed(): void
    {
        // The walk is channel-scoped: seeded from another channel's show it
        // would import that channel's history under this one.
        $other = WhatnotChannel::create(['name' => 'Other Channel', 'status' => 'active']);

        Show::create([
            'whatnot_channel_id' => $other->id,
            'title'              => 'Someone else show',
            'show_date'          => '2026-08-20',
            'detail_url'         => 'https://www.whatnot.com/dashboard/live/' . self::UUID,
            'created_by'         => 1,
        ]);

        $this->assertNull($this->scraper()->seedLiveIdFor($this->channel));
    }

    public function test_a_failed_row_stamped_with_another_channel_is_ignored(): void
    {
        // Seeding across channels does not fail loudly — the walk runs happily
        // and imports the wrong channel's history under this one.
        $other = WhatnotChannel::create(['name' => 'Other Channel', 'status' => 'active']);

        ShowIngestionLog::create([
            'source'      => 'whatnot',
            'status'      => 'failed',
            'raw_payload' => [
                'title'       => 'Their show',
                'detail_url'  => 'https://www.whatnot.com/dashboard/live/' . self::UUID,
                '_channel_id' => $other->id,
            ],
        ]);

        $this->assertNull($this->scraper()->seedLiveIdFor($this->channel));
    }

    public function test_a_failed_row_stamped_with_this_channel_is_used(): void
    {
        WhatnotChannel::create(['name' => 'Other Channel', 'status' => 'active']);

        ShowIngestionLog::create([
            'source'      => 'whatnot',
            'status'      => 'failed',
            'raw_payload' => [
                'title'       => 'Our show',
                'detail_url'  => 'https://www.whatnot.com/dashboard/live/' . self::UUID,
                '_channel_id' => $this->channel->id,
            ],
        ]);

        $this->assertSame(self::UUID, $this->scraper()->seedLiveIdFor($this->channel));
    }

    public function test_an_unstamped_row_is_refused_once_there_is_more_than_one_channel(): void
    {
        // Rows written before the stamp existed carry no channel at all. With
        // one channel there is only one place they can have come from; with
        // two, guessing is how the wrong history gets imported.
        WhatnotChannel::create(['name' => 'Other Channel', 'status' => 'active']);

        ShowIngestionLog::create([
            'source'      => 'whatnot',
            'status'      => 'failed',
            'raw_payload' => [
                'title'      => 'Legacy row',
                'detail_url' => 'https://www.whatnot.com/dashboard/live/' . self::UUID,
            ],
        ]);

        $this->assertNull($this->scraper()->seedLiveIdFor($this->channel));
    }

    public function test_nothing_on_record_yields_no_seed(): void
    {
        $this->assertNull($this->scraper()->seedLiveIdFor($this->channel));
    }
}
