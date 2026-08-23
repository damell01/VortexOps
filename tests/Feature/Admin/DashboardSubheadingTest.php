<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\DashboardImproved as Dashboard;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSubheadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'owner@vortex.com']);
        $this->actingAs(User::factory()->create(['email' => 'owner@vortex.com']));
    }

    public function test_subheading_shows_generic_brand_when_no_channel_selected(): void
    {
        ChannelContext::setActive(null);

        $subheading = (new Dashboard)->getSubheading();

        // The wording around it is marketing copy and has already been
        // rewritten once under this test. What has to hold is that the brand
        // is named when no channel is chosen.
        $this->assertStringContainsString('Vortex Breaks', $subheading);
    }

    public function test_subheading_shows_the_active_channels_name(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        ChannelContext::setActive($channel->id);

        $subheading = (new Dashboard)->getSubheading();

        $this->assertStringContainsString('Vortex Collects', $subheading);
    }

    public function test_subheading_prefers_display_title_over_name_when_set(): void
    {
        $channel = WhatnotChannel::create(['name' => 'vb_main', 'display_title' => 'Vortex Breaks Main', 'status' => 'active']);
        ChannelContext::setActive($channel->id);

        $subheading = (new Dashboard)->getSubheading();

        // display_title wins over name — the point of the test, and the
        // part that would silently break.
        $this->assertStringContainsString('Vortex Breaks Main', $subheading);
        $this->assertStringNotContainsString('vb_main', $subheading);
    }
}
