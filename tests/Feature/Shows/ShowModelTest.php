<?php

namespace Tests\Feature\Shows;

use App\Models\DeductionRequest;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeShow(array $attrs = []): Show
    {
        return Show::create(array_merge([
            'title'      => 'Test Show',
            'show_date'  => now()->toDateString(),
            'status'     => 'draft',
            'created_by' => $this->user->id,
        ], $attrs));
    }

    public function test_status_labels_are_complete(): void
    {
        $labels = Show::statusLabels();

        foreach (['draft', 'pending_review', 'mapping', 'pending_approval', 'reconciled', 'closed', 'cancelled'] as $status) {
            $this->assertArrayHasKey($status, $labels, "Status '{$status}' missing from statusLabels()");
            $this->assertNotEmpty($labels[$status]);
        }
    }

    public function test_import_source_labels_are_complete(): void
    {
        $labels = Show::importSourceLabels();

        $this->assertArrayHasKey('manual', $labels);
        $this->assertArrayHasKey('auto_whatnot', $labels);
    }

    public function test_show_belongs_to_channel(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Sports Breaks', 'status' => 'active']);
        $show    = $this->makeShow(['whatnot_channel_id' => $channel->id]);

        $show->load('channel');

        $this->assertInstanceOf(WhatnotChannel::class, $show->channel);
        $this->assertEquals($channel->id, $show->channel->id);
        $this->assertEquals('Sports Breaks', $show->channel->name);
    }

    public function test_show_has_many_streamers(): void
    {
        $show    = $this->makeShow();
        $s1      = Streamer::create(['name' => 'Alice', 'status' => 'active', 'include_tips' => false, 'payout_type' => 'hourly', 'hourly_rate' => 15]);
        $s2      = Streamer::create(['name' => 'Bob',   'status' => 'active', 'include_tips' => false, 'payout_type' => 'flat_rate']);

        $show->streamers()->attach($s1->id, ['is_primary' => true]);
        $show->streamers()->attach($s2->id, ['is_primary' => false]);

        $show->load('streamers');
        $this->assertCount(2, $show->streamers);
    }

    public function test_primary_streamer_returns_pivot_primary(): void
    {
        $show    = $this->makeShow();
        $primary = Streamer::create(['name' => 'Primary', 'status' => 'active', 'include_tips' => false, 'payout_type' => 'hourly', 'hourly_rate' => 20]);
        $other   = Streamer::create(['name' => 'Other',   'status' => 'active', 'include_tips' => false, 'payout_type' => 'flat_rate']);

        $show->streamers()->attach($primary->id, ['is_primary' => true]);
        $show->streamers()->attach($other->id,   ['is_primary' => false]);

        $this->assertEquals($primary->id, $show->primaryStreamer()?->id);
    }

    public function test_primary_streamer_returns_null_when_no_streamers(): void
    {
        $show = $this->makeShow();
        $this->assertNull($show->primaryStreamer());
    }

    public function test_financial_fields_are_cast_to_decimal(): void
    {
        $show = $this->makeShow([
            'gross_revenue' => 1234.567,
            'whatnot_net'   => 1111.22,
            'tips'          => 50.00,
        ]);

        $show->refresh();

        $this->assertEquals('1234.57', $show->gross_revenue);
        $this->assertEquals('1111.22', $show->whatnot_net);
        $this->assertEquals('50.00', $show->tips);
    }

    public function test_show_date_is_cast_to_date_instance(): void
    {
        $show = $this->makeShow(['show_date' => '2026-06-15']);
        $show->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $show->show_date);
        $this->assertEquals('2026-06-15', $show->show_date->toDateString());
    }

    public function test_show_can_be_created_without_optional_fields(): void
    {
        $show = $this->makeShow();

        $this->assertDatabaseHas('shows', [
            'id'     => $show->id,
            'status' => 'draft',
        ]);

        $this->assertNull($show->gross_revenue);
        $this->assertNull($show->whatnot_net);
    }

    private function stepStatus(array $steps, string $key): string
    {
        return collect($steps)->firstWhere('key', $key)['status'];
    }

    public function test_pipeline_steps_for_draft_show(): void
    {
        $show  = $this->makeShow();
        $steps = $show->pipelineSteps();

        $this->assertSame('done', $this->stepStatus($steps, 'created'));
        $this->assertSame('pending', $this->stepStatus($steps, 'mapped'));
        $this->assertSame('pending', $this->stepStatus($steps, 'deduction_approved'));
        $this->assertSame('pending', $this->stepStatus($steps, 'streamer_reviewed'));
        $this->assertSame('pending', $this->stepStatus($steps, 'log_approved'));
        $this->assertSame('pending', $this->stepStatus($steps, 'payout'));
    }

    public function test_pipeline_steps_for_show_pending_approval(): void
    {
        $show     = $this->makeShow(['status' => 'pending_approval']);
        $streamer = Streamer::create(['name' => 'Alice', 'status' => 'active', 'include_tips' => false, 'payout_type' => 'hourly', 'hourly_rate' => 15]);
        DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        $steps = $show->pipelineSteps();

        $this->assertSame('done', $this->stepStatus($steps, 'mapped'));
        $this->assertSame('current', $this->stepStatus($steps, 'deduction_approved'));
        $this->assertSame('pending', $this->stepStatus($steps, 'streamer_reviewed'));
    }

    public function test_pipeline_steps_for_reconciled_show_awaiting_streamer_review(): void
    {
        $show     = $this->makeShow(['status' => 'reconciled']);
        $streamer = Streamer::create(['name' => 'Alice', 'status' => 'active', 'include_tips' => false, 'payout_type' => 'hourly', 'hourly_rate' => 15]);
        DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'processed']);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        $steps = $show->pipelineSteps();

        $this->assertSame('done', $this->stepStatus($steps, 'deduction_approved'));
        $this->assertSame('current', $this->stepStatus($steps, 'streamer_reviewed'));
        $this->assertSame('pending', $this->stepStatus($steps, 'log_approved'));
    }

    public function test_pipeline_steps_for_fully_completed_show(): void
    {
        $show     = $this->makeShow(['status' => 'reconciled']);
        $streamer = Streamer::create(['name' => 'Bob', 'status' => 'active', 'include_tips' => false, 'payout_type' => 'flat_rate']);
        DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'processed']);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved']);
        Payout::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'payout_type' => 'flat_rate', 'calculated_payout' => 50, 'status' => 'draft']);

        $steps = $show->pipelineSteps();

        foreach (['created', 'mapped', 'deduction_approved', 'streamer_reviewed', 'log_approved', 'payout'] as $key) {
            $this->assertSame('done', $this->stepStatus($steps, $key), "Expected step '{$key}' to be done");
        }
    }

    public function test_pipeline_steps_for_cancelled_show_are_skipped(): void
    {
        $show  = $this->makeShow(['status' => 'cancelled']);
        $steps = $show->pipelineSteps();

        $this->assertSame('done', $this->stepStatus($steps, 'created'));
        foreach (['mapped', 'deduction_approved', 'streamer_reviewed', 'log_approved', 'payout'] as $key) {
            $this->assertSame('skipped', $this->stepStatus($steps, $key), "Expected step '{$key}' to be skipped");
        }
    }
}
