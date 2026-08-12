<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\ShowResource\Pages\ViewShow;
use App\Filament\Resources\ShowResource\RelationManagers\OrdersRelationManager;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrichmentGatingAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'owner@test.com']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
    }

    private function showWithUrl(User $creator): Show
    {
        return Show::create([
            'title' => 'Gated Show', 'show_date' => now()->toDateString(),
            'status' => 'draft', 'created_by' => $creator->id,
            'detail_url' => 'https://www.whatnot.com/show/x',
        ]);
    }

    private function rm(Show $show)
    {
        return Livewire::test(OrdersRelationManager::class, [
            'ownerRecord' => $show,
            'pageClass'   => ViewShow::class,
        ]);
    }

    // ── Action gating ─────────────────────────────────────────────────────────

    public function test_streamer_cannot_see_admin_only_enrichment_actions(): void
    {
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $user = User::factory()->create(['email' => 's@test.com']);
        $user->assignRole('streamer');
        $streamer->update(['user_id' => $user->id]);

        $show = $this->showWithUrl($user);
        $show->streamers()->attach($streamer->id);

        Livewire::actingAs($user);

        $this->rm($show)
            ->assertTableActionHidden('import_orders')     // triggers a scrape
            ->assertTableActionHidden('add_inventory_item') // writes shared catalogue
            ->assertTableActionVisible('fill_costs');       // still theirs to use
    }

    public function test_admin_sees_all_enrichment_actions(): void
    {
        $admin = User::factory()->create(['email' => 'a@test.com']);
        $admin->assignRole('admin');
        $show = $this->showWithUrl($admin);

        Livewire::actingAs($admin);

        $this->rm($show)
            ->assertTableActionVisible('import_orders')
            ->assertTableActionVisible('add_inventory_item')
            ->assertTableActionVisible('fill_costs');
    }

    // ── Status-change audit ───────────────────────────────────────────────────

    public function test_status_transition_is_audited_with_old_and_new(): void
    {
        $admin = User::factory()->create(['email' => 'a@test.com']);
        $this->actingAs($admin);

        $show = Show::create([
            'title' => 'Audited', 'show_date' => now()->toDateString(),
            'status' => 'draft', 'created_by' => $admin->id,
        ]);

        $show->update(['status' => 'pending_review']);

        $activity = Activity::where('subject_id', $show->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('draft', data_get($activity->properties, 'old.status'));
        $this->assertEquals('pending_review', data_get($activity->properties, 'attributes.status'));
        $this->assertEquals($admin->id, $activity->causer_id);
    }

    public function test_non_status_update_does_not_create_status_audit(): void
    {
        $admin = User::factory()->create(['email' => 'a@test.com']);
        $this->actingAs($admin);

        $show = Show::create([
            'title' => 'Quiet', 'show_date' => now()->toDateString(),
            'status' => 'draft', 'created_by' => $admin->id,
        ]);

        $show->update(['gross_revenue' => 500]); // no status change

        // Show only audits the status field, so a metric-only update logs nothing.
        $this->assertEquals(0, Activity::where('subject_id', $show->id)->where('event', 'updated')->count());
    }
}
