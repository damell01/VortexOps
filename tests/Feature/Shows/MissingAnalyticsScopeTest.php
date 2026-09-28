<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A show flagged with Show::NO_ACTIVITY_FLAG has already been established,
 * on the ground, to have nothing to fetch — 12+ hours past air time, zero
 * orders/shipments/units/gross/net. Before this, scopeMissingAnalytics() had
 * no way to know that and kept re-targeting it on every sync forever, which
 * is exactly the "no need to retry it" complaint: a show this reconciler had
 * already given up on for good reason kept eating a scrape attempt anyway.
 */
class MissingAnalyticsScopeTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = User::factory()->create()->id;
    }

    private function show(array $overrides = []): Show
    {
        return Show::create(array_merge([
            'title' => 'Test show',
            'show_date' => today()->subDays(3)->toDateString(),
            'created_by' => $this->userId,
        ], $overrides));
    }

    public function test_a_never_checked_show_is_due(): void
    {
        $show = $this->show();

        $this->assertTrue(Show::missingAnalytics()->pluck('id')->contains($show->id));
    }

    public function test_a_show_flagged_with_no_activity_is_no_longer_due(): void
    {
        $show = $this->show(['notes' => Show::NO_ACTIVITY_FLAG]);

        $this->assertFalse(Show::missingAnalytics()->pluck('id')->contains($show->id));
    }

    public function test_the_flag_still_excludes_when_appended_after_other_notes(): void
    {
        // reconcileEndedShowState() appends the flag with trim($notes."\n".$flag),
        // so it is never the only thing in the column in practice.
        $show = $this->show(['notes' => "Streamer said internet was down.\n".Show::NO_ACTIVITY_FLAG]);

        $this->assertFalse(Show::missingAnalytics()->pluck('id')->contains($show->id));
    }

    public function test_an_unrelated_note_does_not_accidentally_exclude_a_show(): void
    {
        $show = $this->show(['notes' => 'Streamer requested a reschedule.']);

        $this->assertTrue(Show::missingAnalytics()->pluck('id')->contains($show->id));
    }
}
