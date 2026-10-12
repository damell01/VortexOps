<?php

namespace Tests\Feature\Reliability;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Services\ChannelSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelSuggesterTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $breaks;
    private WhatnotChannel $collects;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $this->collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'whatnot_username' => 'VortexCollects', 'status' => 'active']);
    }

    private function show(array $data, array $streamers = []): Show
    {
        $show = Show::withoutEvents(fn () => Show::create(array_merge([
            'title' => 'Show', 'show_date' => '2026-07-02', 'status' => 'draft',
            'whatnot_channel_id' => $this->breaks->id, 'channel_attribution_suspect' => false,
        ], $data)));
        foreach ($streamers as $streamer) {
            $show->streamers()->attach($streamer->id);
        }

        return $show->load('streamers');
    }

    private function history(Streamer $streamer, WhatnotChannel $channel, int $count, ?string $start = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->show(['title' => "History {$i}", 'whatnot_channel_id' => $channel->id, 'start_time' => $start], [$streamer]);
        }
    }

    private function streamer(string $name): Streamer
    {
        return Streamer::create(['name' => $name]);
    }

    public function test_title_naming_the_channel_is_high_confidence(): void
    {
        $show = $this->show(['title' => 'FREE BOOSTER PACKS /$1 starts W/Dennis_VortexCollects', 'channel_attribution_suspect' => true]);

        $s = (new ChannelSuggester)->suggest($show);

        $this->assertSame($this->collects->id, $s['channel_id']);
        $this->assertSame(ChannelSuggester::HIGH, $s['level']);
        $this->assertStringContainsString('Title names Vortex Collects', $s['reasons'][0]);
    }

    public function test_generic_words_in_a_title_do_not_count_as_naming_a_channel(): void
    {
        $show = $this->show(['title' => 'Big case BREAKS and Vortex cards', 'channel_attribution_suspect' => true]);

        $this->assertNull((new ChannelSuggester)->suggest($show));
    }

    public function test_consistent_streamer_history_is_high_and_mixed_history_is_not(): void
    {
        $dennis = $this->streamer('Dennis');
        $this->history($dennis, $this->collects, 9);
        $this->history($dennis, $this->breaks, 1);
        $flagged = $this->show(['title' => 'Trail Blazing Thursday', 'channel_attribution_suspect' => true], [$dennis]);

        $s = (new ChannelSuggester)->suggest($flagged);
        $this->assertSame($this->collects->id, $s['channel_id']);
        $this->assertSame(ChannelSuggester::HIGH, $s['level']);
        $this->assertStringContainsString('Dennis: 9 of 10 confirmed shows on Vortex Collects', implode(' ', $s['reasons']));

        $cate = $this->streamer('Cate');
        $this->history($cate, $this->collects, 3);
        $this->history($cate, $this->breaks, 2);
        $mixed = $this->show(['title' => 'Friday night', 'channel_attribution_suspect' => true], [$cate]);
        $this->assertSame(ChannelSuggester::LOW, (new ChannelSuggester)->suggest($mixed)['level']);
    }

    public function test_flagged_shows_do_not_count_as_history(): void
    {
        $dennis = $this->streamer('Dennis');
        for ($i = 0; $i < 5; $i++) {
            $this->show(['title' => "Unconfirmed {$i}", 'channel_attribution_suspect' => true], [$dennis]);
        }
        $flagged = $this->show(['title' => 'Another', 'channel_attribution_suspect' => true], [$dennis]);

        $this->assertNull((new ChannelSuggester)->suggest($flagged));
    }

    public function test_time_slot_alone_is_never_high_confidence(): void
    {
        $nobody = $this->streamer('Nobody');
        $this->history($nobody, $this->collects, 6, '2026-07-02 19:00:00');
        $flagged = $this->show(['title' => 'Untitled', 'start_time' => '2026-07-09 19:30:00', 'channel_attribution_suspect' => true]);

        $s = (new ChannelSuggester)->suggest($flagged);
        $this->assertSame($this->collects->id, $s['channel_id']);
        $this->assertSame(ChannelSuggester::MEDIUM, $s['level']);
    }

    public function test_suggestion_outside_the_imported_channels_is_downgraded(): void
    {
        $shop = WhatnotChannel::create(['name' => 'Vortex Shop', 'status' => 'active']);
        $show = $this->show(['title' => 'w/Dennis_VortexCollects', 'channel_attribution_suspect' => true]);
        foreach ([$this->breaks, $shop] as $channel) {
            ShowIngestionLog::create(['show_id' => $show->id, 'whatnot_channel_id' => $channel->id, 'source' => 'whatnot', 'status' => 'success', 'raw_payload' => []]);
        }

        $s = (new ChannelSuggester)->suggest($show);
        $this->assertSame($this->collects->id, $s['channel_id']);
        $this->assertSame(ChannelSuggester::MEDIUM, $s['level']);
        $this->assertStringContainsString('not imported under Vortex Collects', implode(' ', $s['reasons']));
    }
}
