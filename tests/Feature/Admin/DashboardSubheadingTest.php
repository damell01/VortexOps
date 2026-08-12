<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Dashboard;
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

        $this->assertStringContainsString('Overview of Vortex Breaks operations', $subheading);
    }

    public function test_subheading_shows_the_active_channels_name(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        ChannelContext::setActive($channel->id);

        $subheading = (new Dashboard)->getSubheading();

        $this->assertStringContainsString('Overview of Vortex Collects operations', $subheading);
    }

    public function test_subheading_prefers_display_title_over_name_when_set(): void
    {
        $channel = WhatnotChannel::create(['name' => 'vb_main', 'display_title' => 'Vortex Breaks Main', 'status' => 'active']);
        ChannelContext::setActive($channel->id);

        $subheading = (new Dashboard)->getSubheading();

        $this->assertStringContainsString('Overview of Vortex Breaks Main operations', $subheading);
    }
}
