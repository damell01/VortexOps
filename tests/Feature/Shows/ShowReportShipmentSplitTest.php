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
 * Streamers only give hours (and items) on their show report: shipments are
 * counted from the show. An admin reviewing it can correct shipments and, for
 * someone paid per envelope and per package, the split.
 */
class ShowReportShipmentSplitTest extends TestCase
{
    use RefreshDatabase;

    private function reportFor(string $payoutType): array
    {
        Role::findOrCreate('streamer', 'web');
        $user = User::factory()->create(['email' => "{$payoutType}@example.com"]);
        $user->assignRole('streamer');
        $streamer = Streamer::updateOrCreate(['user_id' => $user->id], ['user_id' => $user->id, 'name' => 'Jay', 'email' => $user->email, 'status' => 'active', 'payout_type' => $payoutType]);
        $show = Show::create([
            'whatnot_channel_id' => WhatnotChannel::create(['name' => 'Estill', 'status' => 'active'])->id,
            'title' => 'Estill Night', 'show_date' => now()->subDay()->toDateString(), 'status' => 'mapping',
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        return [Livewire::actingAs($user->fresh())->test(EndOfStreamForm::class, ['showId' => (string) $show->id]), $show, $user];
    }

    public function test_streamers_only_give_hours_and_never_fill_in_shipments(): void
    {
        [$page] = $this->reportFor('shift_share');

        $page->assertSeeHtml('id="eos-hours"')->assertDontSeeHtml('id="eos-pwe"')->assertDontSeeHtml('id="eos-packages"')
            ->assertDontSeeHtml('wire:model.live.debounce.500ms="shipments"')->assertSee('Counted from the show for you');
    }

    public function test_an_admin_enters_envelopes_and_packages_for_an_hourly_streamer_and_they_save(): void
    {
        [, $show] = $this->reportFor('shift_share');
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $page = Livewire::actingAs($admin)->test(EndOfStreamForm::class, ['showId' => (string) $show->id]);

        $page->assertSeeHtml('id="eos-pwe"')->assertSeeHtml('id="eos-packages"')
            ->set('shipments', '15')->set('pweCount', '10')->set('labelCount', '5');

        $entry = StreamerLogEntry::where('show_id', $show->id)->first();
        $this->assertSame(10, $entry->pwe_count);
        $this->assertSame(5, $entry->label_count);
    }

    public function test_a_profit_share_streamer_has_no_envelope_or_package_split(): void
    {
        [$page] = $this->reportFor('profit_share');

        $page->assertSeeHtml('eos-hours')->assertDontSeeHtml('id="eos-pwe"')->assertDontSeeHtml('id="eos-packages"');
    }
}
