<?php

namespace Tests\Feature\Reliability;

use App\Filament\Resources\ShowResource;
use App\Filament\Resources\ShowResource\Pages\ReviewShowChannels;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChannelReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        $this->enableAdminModules();
    }

    private function show(array $data = []): Show
    {
        return Show::withoutEvents(fn () => Show::create(array_merge([
            'title' => 'Flagged history', 'show_date' => '2026-07-05', 'status' => 'draft',
            'channel_attribution_suspect' => true,
        ], $data)));
    }

    public function test_direct_queue_shows_flagged_history_across_channels_without_revenue_columns(): void
    {
        $this->admin();
        $a = WhatnotChannel::create(['name' => 'Cards', 'status' => 'active']);
        $b = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);
        ChannelContext::setActive($a->id);
        $historical = $this->show(['whatnot_channel_id' => $b->id]);
        $historical->forceFill(['is_operational' => false])->saveQuietly();
        $clear = $this->show(['title' => 'Already confirmed', 'channel_attribution_suspect' => false]);
        Livewire::test(ReviewShowChannels::class)
            ->assertCanSeeTableRecords([$historical])->assertCanNotSeeTableRecords([$clear])
            ->assertSee('Assigned channel:')->assertSee('Breaks')->assertSee('Confirm Channel')
            ->assertDontSee('Whatnot Gross')->assertDontSee('Operational Revenue');
        $this->get(ShowResource::getUrl('channel-review'))->assertOk();
        $menu = view('filament.components.desktop-mega-nav')->render();
        $this->assertStringContainsString(ShowResource::getUrl('channel-review'), $menu);
        ChannelContext::setActive(null);
    }

    public function test_card_lists_the_other_channels_the_show_was_imported_under(): void
    {
        $this->admin();
        $breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        $unrelated = WhatnotChannel::create(['name' => 'Vortex Shop', 'status' => 'active']);
        $show = $this->show(['whatnot_channel_id' => $breaks->id]);
        foreach ([$breaks, $collects, $collects] as $channel) {
            ShowIngestionLog::create(['show_id' => $show->id, 'whatnot_channel_id' => $channel->id, 'source' => 'whatnot', 'status' => 'success', 'raw_payload' => []]);
        }
        $unknown = $this->show(['title' => 'No second channel logged', 'whatnot_channel_id' => $breaks->id]);

        Livewire::test(ReviewShowChannels::class)
            ->assertSee('Also imported under:')->assertSee('Vortex Collects')
            ->assertSee('Imported under 2 channels')
            ->assertDontSee('Vortex Shop')
            ->assertSee('Other channel not recorded for this import');
        $this->assertNotNull($unknown);
    }

    public function test_confirming_channel_moves_show_and_removes_it_from_queue(): void
    {
        $this->admin();
        $channel = WhatnotChannel::create(['name' => 'Correct channel', 'status' => 'active']);
        $show = $this->show();
        Livewire::test(ReviewShowChannels::class)
            ->callTableAction('confirm_channel', $show, ['whatnot_channel_id' => $channel->id])
            ->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$show]);
        $this->assertSame($channel->id, $show->fresh()->whatnot_channel_id);
        $this->assertFalse($show->fresh()->channel_attribution_suspect);
    }

    public function test_empty_queue_stays_empty_and_streamers_cannot_access_it(): void
    {
        $this->admin();
        $this->show(['channel_attribution_suspect' => false]);
        Livewire::test(ReviewShowChannels::class)->assertSee('No channels need review')->assertCountTableRecords(0);
        Role::findOrCreate('streamer', 'web');
        $user = User::factory()->create(); $user->assignRole('streamer');
        $this->actingAs($user);
        $this->assertFalse(ReviewShowChannels::canAccess());
        $this->get(ShowResource::getUrl('channel-review'))->assertForbidden();
    }
}
