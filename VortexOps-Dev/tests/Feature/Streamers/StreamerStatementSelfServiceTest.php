<?php

namespace Tests\Feature\Streamers;

use App\Filament\Pages\StreamerStatement;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamerStatementSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.owner_email' => 'owner@vortex.com']);
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
    }

    public function test_streamer_can_access_and_only_ever_sees_their_own_statement(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);

        $me = User::factory()->create();
        $me->assignRole('streamer');
        $myStreamer = Streamer::create(['name' => 'Me', 'status' => 'active', 'include_tips' => false, 'user_id' => $me->id]);

        $otherStreamer = Streamer::create(['name' => 'Other', 'status' => 'active', 'include_tips' => false]);

        $myShow = Show::create([
            'whatnot_channel_id' => $channel->id, 'title' => 'My Show', 'show_date' => now()->toDateString(),
            'gross_revenue' => 1000, 'whatnot_net' => 900, 'status' => 'reconciled', 'created_by' => $me->id,
        ]);
        $otherShow = Show::create([
            'whatnot_channel_id' => $channel->id, 'title' => 'Other Show', 'show_date' => now()->toDateString(),
            'gross_revenue' => 1000, 'whatnot_net' => 900, 'status' => 'reconciled', 'created_by' => $me->id,
        ]);
        $myShow->streamers()->attach($myStreamer->id, ['is_primary' => true]);
        $otherShow->streamers()->attach($otherStreamer->id, ['is_primary' => true]);

        Payout::create(['streamer_id' => $myStreamer->id, 'show_id' => $myShow->id, 'payout_type' => 'profit_share', 'calculated_payout' => 111, 'status' => 'draft']);
        Payout::create(['streamer_id' => $otherStreamer->id, 'show_id' => $otherShow->id, 'payout_type' => 'profit_share', 'calculated_payout' => 222, 'status' => 'draft']);

        $this->actingAs($me);
        $this->assertTrue(StreamerStatement::canAccess());

        $component = Livewire::test(StreamerStatement::class);
        $instance  = $component->instance();

        $this->assertTrue($instance->getIsSelfServiceProperty(), 'isSelfService should be true');
        $this->assertEquals($myStreamer->id, $instance->streamerId, 'mount() should default streamerId to own profile');

        // Even if a tampered request tries to set someone else's streamerId,
        // the effective query is always forced back to the caller's own profile.
        $instance->streamerId = $otherStreamer->id;

        $data = $instance->getStatementDataProperty();
        $this->assertCount(1, $data['shows']);
        $this->assertEquals('My Show', $data['shows'][0]['title']);
        $this->assertEquals(111.0, $data['totals']['due']);

        $this->assertEquals($myStreamer->id, $instance->getSelectedStreamerProperty()?->id);
        $this->assertCount(1, $instance->getStreamersListProperty());
    }
}
