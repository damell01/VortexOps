<?php

namespace Tests\Unit;

use App\Models\WhatnotChannel;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_is_null_when_nothing_selected(): void
    {
        $this->assertNull(ChannelContext::current());
        $this->assertNull(ChannelContext::currentId());
        $this->assertFalse(ChannelContext::isScoped());
    }

    public function test_set_active_scopes_to_that_channel(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);

        ChannelContext::setActive($channel->id);

        $this->assertTrue(ChannelContext::isScoped());
        $this->assertSame($channel->id, ChannelContext::currentId());
        $this->assertTrue(ChannelContext::current()->is($channel));
    }

    public function test_set_active_null_clears_the_scope(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        ChannelContext::setActive($channel->id);

        ChannelContext::setActive(null);

        $this->assertFalse(ChannelContext::isScoped());
        $this->assertNull(ChannelContext::current());
    }

    public function test_available_only_returns_active_channels_sorted_by_name(): void
    {
        WhatnotChannel::create(['name' => 'Zeta Channel', 'status' => 'active']);
        WhatnotChannel::create(['name' => 'Alpha Channel', 'status' => 'active']);
        WhatnotChannel::create(['name' => 'Inactive Channel', 'status' => 'inactive']);

        $names = ChannelContext::available()->pluck('name')->all();

        $this->assertSame(['Alpha Channel', 'Zeta Channel'], $names);
    }
}
