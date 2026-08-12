<?php

namespace Tests\Feature\Admin;

use App\Models\Payout;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The AuditsUpdates trait records a populated old → new diff on update (the
 * LogsActivity auto-diff stores empty properties on activitylog v5.0.0).
 */
class AuditsUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private function updatedActivity(string $subjectType, int $id): ?Activity
    {
        return Activity::where('subject_type', $subjectType)
            ->where('subject_id', $id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();
    }

    public function test_payout_update_records_old_and_new_values(): void
    {
        $this->actingAs(User::factory()->create());
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active', 'payout_type' => 'flat_rate']);

        $payout = Payout::create([
            'streamer_id' => $streamer->id, 'payout_type' => 'flat_rate',
            'calculated_payout' => 100, 'status' => 'draft',
        ]);

        $payout->update(['status' => 'approved', 'calculated_payout' => 120]);

        $activity = $this->updatedActivity(Payout::class, $payout->id);

        $this->assertNotNull($activity);
        $this->assertEquals('draft', data_get($activity->properties, 'old.status'));
        $this->assertEquals('approved', data_get($activity->properties, 'attributes.status'));
        $this->assertEquals(120.0, (float) data_get($activity->properties, 'attributes.calculated_payout'));
    }

    public function test_batch_update_records_status_transition(): void
    {
        $this->actingAs($admin = User::factory()->create());

        $batch = WeeklyPayoutBatch::create([
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end'   => now()->endOfWeek()->toDateString(),
            'status'     => 'draft', 'created_by' => $admin->id,
        ]);

        $batch->update(['status' => 'finalized']);

        $activity = $this->updatedActivity(WeeklyPayoutBatch::class, $batch->id);

        $this->assertNotNull($activity);
        $this->assertEquals('draft', data_get($activity->properties, 'old.status'));
        $this->assertEquals('finalized', data_get($activity->properties, 'attributes.status'));
        $this->assertEquals($admin->id, $activity->causer_id);
    }

    public function test_no_duplicate_empty_updated_entry(): void
    {
        $this->actingAs(User::factory()->create());
        $batch = WeeklyPayoutBatch::create([
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end'   => now()->endOfWeek()->toDateString(),
            'status'     => 'draft', 'created_by' => 1,
        ]);

        $batch->update(['status' => 'finalized']);

        // Exactly one "updated" entry (from AuditsUpdates) — LogsActivity's is suppressed.
        $this->assertEquals(1, Activity::where('subject_type', WeeklyPayoutBatch::class)
            ->where('subject_id', $batch->id)
            ->where('event', 'updated')
            ->count());
    }
}
