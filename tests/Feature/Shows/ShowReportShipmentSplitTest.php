<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Someone paid per envelope and per package says how many of each on their
 * show report; anyone else is only asked for shipments.
 */
class ShowReportShipmentSplitTest extends TestCase
{
    use RefreshDatabase;

    private function reportFor(string $payoutType): array
    {
        Role::findOrCreate('streamer', 'web');
        $user = User::factory()->create(['email' => "{$payoutType}@example.com"]);
        $user->assignRole('streamer');
        $streamer = Streamer::create(['user_id' => $user->id, 'name' => 'Jay', 'email' => $user->email, 'status' => 'active', 'payout_type' => $payoutType]);
        $show = Show::create([
            'whatnot_channel_id' => WhatnotChannel::create(['name' => 'Estill', 'status' => 'active'])->id,
            'title' => 'Estill Night', 'show_date' => now()->subDay()->toDateString(), 'status' => 'mapping',
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        return [Livewire::actingAs($user->fresh())->test(EndOfStreamForm::class, ['showId' => (string) $show->id]), $show];
    }

    public function test_an_hourly_streamer_enters_envelopes_and_packages_and_they_save(): void
    {
        [$page, $show] = $this->reportFor('shift_share');

        $page->set('step', 2)->assertSeeHtml('id="eos-pwe"')->assertSeeHtml('id="eos-packages"')
            ->set('shipments', '15')->set('pweCount', '10')->set('labelCount', '5');

        $entry = StreamerLogEntry::where('show_id', $show->id)->first();
        $this->assertSame(10, $entry->pwe_count);
        $this->assertSame(5, $entry->label_count);
    }

    public function test_a_profit_share_streamer_is_only_asked_for_shipments(): void
    {
        [$page] = $this->reportFor('profit_share');

        $page->set('step', 2)->assertSeeHtml('eos-hours')->assertDontSeeHtml('id="eos-pwe"')->assertDontSeeHtml('id="eos-packages"');
    }
}
