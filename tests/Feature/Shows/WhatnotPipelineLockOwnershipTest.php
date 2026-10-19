<?php

namespace Tests\Feature\Shows;

use App\Support\WhatnotPipelineLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatnotPipelineLockOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_contender_cannot_remove_the_owners_status(): void
    {
        $owner = WhatnotPipelineLock::acquire('Owner pipeline');
        $this->assertNotNull($owner);
        try {
            WhatnotPipelineLock::release(Cache::lock(WhatnotPipelineLock::KEY, 60));
            $this->assertSame('Owner pipeline', WhatnotPipelineLock::holder()['label']);
            $this->assertNull(WhatnotPipelineLock::acquire('Contender'));
        } finally {
            WhatnotPipelineLock::release($owner);
        }
        $this->assertNull(WhatnotPipelineLock::holder());
    }

    public function test_wait_times_out_even_when_application_time_is_frozen(): void
    {
        $this->freezeTime();
        $owner = WhatnotPipelineLock::acquire('Owner pipeline');
        $start = hrtime(true);
        try {
            $this->assertNull(WhatnotPipelineLock::acquire('Contender', 1));
            $this->assertLessThan(3, (hrtime(true) - $start) / 1_000_000_000);
        } finally {
            WhatnotPipelineLock::release($owner);
        }
    }
}
