<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShowResource\Pages\ViewShow;
use App\Models\DeductionRequest;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * View Show was one long vertical scroll through 9 sections. Regrouped into
 * a 4-tab layout (Overview / Financials / Analytics / Approval) — same
 * fields, same visibility rules, just organized. The Approval tab only
 * appears once a deduction request exists, matching the section's own
 * pre-existing visibility rule. CreateShow uses its own wizard schema, not
 * ShowResource::form(), so it's untouched by this.
 */
class ViewShowTabsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
    }

    private function makeShow(): Show
    {
        $creator = User::factory()->create();

        return Show::create([
            'title' => 'S', 'show_date' => now()->toDateString(),
            'status' => 'reconciled', 'created_by' => $creator->id,
        ]);
    }

    public function test_view_show_renders_all_four_tabs_when_a_deduction_request_exists(): void
    {
        $this->actingAs($this->owner());
        $show = $this->makeShow();
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        Livewire::test(ViewShow::class, ['record' => $show->id])
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('Financials')
            ->assertSee('Analytics')
            ->assertSee('Approval')
            ->assertSee('Show Details')
            ->assertSee('Gross Revenue');
    }

    public function test_approval_tab_is_hidden_without_a_deduction_request(): void
    {
        $this->actingAs($this->owner());
        $show = $this->makeShow();

        Livewire::test(ViewShow::class, ['record' => $show->id])
            ->assertOk()
            ->assertSee('Overview')
            ->assertDontSee('Approval Summary');
    }
}
