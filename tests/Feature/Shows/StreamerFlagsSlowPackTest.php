<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The streamer flags a show as slow to pack, at the moment they finish it.
 *
 * They know hours before anyone opens the shipment list, and it costs them one
 * tap. Finding out at the packing bench instead costs an afternoon.
 */
class StreamerFlagsSlowPackTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('streamer', 'web');

        $this->user = User::factory()->create(['email' => 'slow-pack@example.test']);
        $this->user->assignRole('streamer');

        $channel = WhatnotChannel::create(['name' => 'Test Channel', 'status' => 'active']);

        $streamer = Streamer::create([
            'user_id'      => $this->user->id,
            'name'         => 'Test Streamer',
            'email'        => 'slow-pack@example.test',
            'status'       => 'active',
            'payout_type'  => 'flat_rate',
            'flat_rate'    => 100,
            'include_tips' => false,
        ]);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Test Show',
            'show_date'          => now()->toDateString(),
            'gross_revenue'      => 500,
            'whatnot_net'        => 450,
            'tips'               => 0,
            'units_sold'         => 10,
            'show_duration'      => 60,
            'status'             => 'mapping',
        ]);

        $this->show->streamers()->attach($streamer->id);
    }

    private function form()
    {
        return Livewire::actingAs($this->user->fresh())
            ->test(EndOfStreamForm::class)
            ->call('selectShow', (string) $this->show->id);
    }

    public function test_the_flag_and_the_note_land_on_the_show(): void
    {
        $this->form()
            ->set('isSlowPack', true)
            ->set('fulfillmentNotes', 'Four oversized boxes, one is fragile.')
            ->call('saveDetails');

        $show = $this->show->fresh();

        $this->assertTrue($show->is_slow_pack);
        $this->assertSame('Four oversized boxes, one is fragile.', $show->fulfillment_notes);
    }

    public function test_reopening_the_form_shows_what_was_already_set(): void
    {
        $this->show->forceFill(['is_slow_pack' => true, 'fulfillment_notes' => 'Heavy.'])->save();

        $this->form()
            ->assertSet('isSlowPack', true)
            ->assertSet('fulfillmentNotes', 'Heavy.');
    }

    public function test_an_empty_note_is_stored_as_nothing_rather_than_an_empty_string(): void
    {
        // Otherwise every show carries a note that is not a note, and the
        // fulfillment list shows a marker for all of them.
        $this->show->forceFill(['fulfillment_notes' => 'Old note.'])->save();

        $this->form()->set('fulfillmentNotes', '')->call('saveDetails');

        $this->assertNull($this->show->fresh()->fulfillment_notes);
    }

    public function test_the_whatnot_count_comparison_is_gone_from_this_screen(): void
    {
        // It compared orders on Whatnot with inventory units in the report —
        // two numbers that legitimately differ — and every streamer read it as
        // an accusation that their report was wrong.
        $this->form()->assertDontSee('Whatnot reference differences');

        $this->assertFalse(
            method_exists(EndOfStreamForm::class, 'getReconciliationWarningsProperty'),
            'the comparison property is still there to be rendered again by accident',
        );
    }
}
