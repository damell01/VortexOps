<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\ShowResource\Pages\EditShow;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Support\StreamerMatchReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "CHINESE SEALED STREAM GO! W/ JAY VCX" came up assigned to Joshua Beaupre,
 * with nothing on screen saying why. Edit Show now says.
 */
class StreamerMatchReasonTest extends TestCase
{
    use RefreshDatabase;

    private function show(string $title, Streamer $streamer): Show
    {
        $show = Show::create(['title' => $title, 'show_date' => '2026-10-20', 'status' => 'draft']);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        return $show;
    }

    public function test_an_alias_match_says_which_alias_and_where_it_came_from(): void
    {
        $joshua = Streamer::create(['name' => 'Joshua Beaupre', 'status' => 'active']);
        $joshua->aliases()->create(['alias' => 'Jay', 'normalized_alias' => 'jay', 'source' => 'show_title']);

        $lines = StreamerMatchReason::lines($this->show('🧧CHINESE SEALED STREAM GO! W/ JAY VCX', $joshua));

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Joshua Beaupre: the alias “Jay”', $lines[0]);
        $this->assertStringContainsString('learned automatically from an earlier show title', $lines[0]);
    }

    public function test_a_hand_pick_the_title_disagrees_with_is_flagged(): void
    {
        $joshua = Streamer::create(['name' => 'Joshua Beaupre', 'status' => 'active']);

        $lines = StreamerMatchReason::lines($this->show('CHINESE SEALED STREAM GO! W/ JAY VCX', $joshua));

        $this->assertSame('Joshua Beaupre: picked by hand — the title doesn’t name them', $lines[0]);
        $this->assertStringContainsString('The title names “JAY VCX”', $lines[1]);
    }

    public function test_a_name_match_and_the_edit_page_show_the_reason(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $jay = Streamer::create(['name' => 'Jay', 'status' => 'active']);
        $show = $this->show('Big rip w/ Jay', $jay);

        $this->assertSame(['Jay: their name is in the title'], StreamerMatchReason::lines($show));
        Livewire::test(EditShow::class, ['record' => $show->id])->assertSee('Jay: their name is in the title');
    }

    public function test_the_reason_the_importer_saved_is_shown(): void
    {
        $joshua = Streamer::create(['name' => 'Joshua Beaupre', 'status' => 'active']);
        $show = $this->show('CHINESE SEALED STREAM GO! W/ JAY VCX', $joshua);
        $show->updateQuietly(['ai_streamer_suggestion' => [['streamer_id' => $joshua->id, 'confidence' => 'high', 'reason' => 'Saved streamer alias @ese']]]);

        $lines = StreamerMatchReason::lines($show->fresh());

        $this->assertSame('Joshua Beaupre: matched automatically when imported (Saved streamer alias @ese)', $lines[0]);
        $this->assertStringContainsString('The title names “JAY VCX”', $lines[1]);
    }

    public function test_the_sync_alias_pass_matches_whole_words_of_the_title_only(): void
    {
        $channel = \App\Models\WhatnotChannel::create(['name' => 'Vortex Cards', 'whatnot_username' => 'vortexcards', 'status' => 'active']);
        $joshua = Streamer::create(['name' => 'Joshua Beaupre', 'status' => 'active']);
        $joshua->aliases()->create(['alias' => 'ese', 'normalized_alias' => 'ese', 'source' => 'manual']);
        $jay = Streamer::create(['name' => 'Jay Smith', 'status' => 'active']);
        $jay->aliases()->create(['alias' => 'Jay VCX', 'normalized_alias' => 'jay vcx', 'source' => 'manual']);
        $chinese = Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'CHINESE SEALED STREAM GO! W/ JAY VCX', 'show_date' => '2026-10-20', 'status' => 'draft', 'raw_import_payload' => ['detail_url' => 'https://www.whatnot.com/live/ese-1234']]);

        $pass = new \ReflectionMethod(\App\Services\WhatnotSyncEngine::class, 'applyKnownStreamerAliases');
        $pass->invoke(app(\App\Services\WhatnotSyncEngine::class), $channel);

        $this->assertSame(['Jay Smith'], $chinese->fresh()->streamers->pluck('name')->all());
    }
}
