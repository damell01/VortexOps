<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\WhatnotSyncPage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The failed-run output lived behind a native <details> toggle on a page that
 * polls every 3 seconds. Livewire's re-render never carries an `open`
 * attribute (Blade has no state for it), so the toggle snapped shut on the
 * next poll — often before a person finished reading it. The fix moved the
 * toggle into Alpine's own state, which survives the morph.
 */
class WhatnotSyncPageOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_run_output_toggle_uses_alpine_state_not_a_native_details_element(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        Setting::set('whatnot_ui_job', json_encode([
            'mode' => 'analytics',
            'status' => 'failed',
            'phase' => 'Pipeline finished with scraper/import errors',
            'error' => 'Detected: analytics backfill failed:',
            'output' => 'analytics backfill failed: session expired, run whatnot:login',
        ]));

        $html = Livewire::test(WhatnotSyncPage::class)->assertOk()->html();

        $this->assertStringContainsString('session expired, run whatnot:login', $html);
        $this->assertStringNotContainsString('<details', $html, 'the output toggle should not be a native <details> element — it loses state on every wire:poll re-render');
    }
}
