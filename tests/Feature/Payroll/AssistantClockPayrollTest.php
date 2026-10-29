<?php
namespace Tests\Feature\Payroll;

use App\Models\{Business, Payout, Streamer, TimeEntry, User, WeeklyPayoutBatch};
use App\Services\PayRunAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantClockPayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_hourly_pay_syncs_approved_clocked_minutes_without_duplicates(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']);
        $this->actingAs($owner);
        $business = Business::create(['name' => 'Assistant payroll fixture']);
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('streamer_assistant', 'web'));
        $member = Streamer::create(['user_id' => $user->id, 'business_id' => $business->id, 'name' => 'Assistant', 'member_type' => 'assistant', 'payout_type' => 'hourly', 'hourly_rate' => 17, 'status' => 'active']);
        $run = WeeklyPayoutBatch::create(['business_id' => $business->id, 'week_start' => '2026-10-19', 'week_end' => '2026-10-25', 'status' => 'draft']);
        $entry = TimeEntry::create(['user_id' => $user->id, 'clocked_in_at' => '2026-10-20 09:00', 'clocked_out_at' => '2026-10-20 10:01', 'reason' => 'other', 'status' => 'ok']);
        TimeEntry::create(['user_id' => $user->id, 'clocked_in_at' => '2026-10-21 09:00', 'clocked_out_at' => '2026-10-21 11:00', 'reason' => 'other', 'status' => 'flagged']);
        $service = app(PayRunAutomationService::class);
        $service->syncWeek('2026-10-19', true, $business->id);
        $line = Payout::where('weekly_payout_batch_id', $run->id)->where('streamer_id', $member->id)->where('payout_type', 'clocked_time')->sole();
        $this->assertEquals(17.28, (float) $line->calculated_payout);
        $entry->update(['status' => 'approved', 'approved_minutes' => 120]);
        $service->syncWeek('2026-10-19', true, $business->id);
        $this->assertEquals(34, (float) $line->fresh()->calculated_payout);
        $this->assertSame(1, Payout::where('weekly_payout_batch_id', $run->id)->where('streamer_id', $member->id)->count());
    }
}
