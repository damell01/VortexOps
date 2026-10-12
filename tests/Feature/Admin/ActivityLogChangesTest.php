<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ActivityLogResource;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * An "updated" entry shows what changed. AuditsUpdates writes old/new into
 * `properties` while `attribute_changes` sits empty beside it; the page used
 * to read the empty one and print "[]".
 */
class ActivityLogChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_update_shows_each_field_with_before_and_after(): void
    {
        $this->actingAs(User::factory()->create());
        $streamer = Streamer::create(['name' => 'Micha', 'status' => 'active', 'payout_type' => 'hourly', 'hourly_rate' => 15]);
        $streamer->update(['hourly_rate' => 18, 'status' => 'inactive']);

        $activity = Activity::where('subject_id', $streamer->id)->where('event', 'updated')->latest('id')->firstOrFail();

        ['old' => $old, 'new' => $new] = ActivityLogResource::changesFor($activity);
        $this->assertEquals('active', $old['status']);
        $this->assertEquals('inactive', $new['status']);

        $html = (string) ActivityLogResource::changesHtml($activity);
        $this->assertStringContainsString('Before', $html);
        $this->assertStringContainsString('After', $html);
        $this->assertStringContainsString('Hourly rate', $html);
        $this->assertStringContainsString('>18<', $html);
        $this->assertStringContainsString('inactive', $html);
        $this->assertStringNotContainsString('[]', $html);

        $this->assertStringContainsString('Status', ActivityLogResource::changedFieldsSummary($activity));
    }

    public function test_a_created_entry_lists_values_without_a_before_column(): void
    {
        $this->actingAs(User::factory()->create());
        $streamer = Streamer::create(['name' => 'New One', 'status' => 'active', 'payout_type' => 'hourly']);

        $activity = Activity::where('subject_id', $streamer->id)->where('event', 'created')->latest('id')->firstOrFail();
        $html = (string) ActivityLogResource::changesHtml($activity);

        $this->assertStringContainsString('New One', $html);
        $this->assertStringNotContainsString('Before', $html);
    }

    public function test_an_entry_with_nothing_recorded_says_so(): void
    {
        $activity = activity()->log('updated');

        $this->assertStringContainsString('No field changes', (string) ActivityLogResource::changesHtml($activity));
        $this->assertNull(ActivityLogResource::changedFieldsSummary($activity));
    }
}
