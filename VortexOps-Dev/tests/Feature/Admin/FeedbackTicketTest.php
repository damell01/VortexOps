<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\FeedbackTicketResource;
use App\Filament\Resources\FeedbackTicketResource\Pages\ViewFeedbackTicket;
use App\Filament\Resources\FeedbackTicketResource\RelationManagers\CommentsRelationManager;
use App\Models\FeedbackTicket;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Super admin (owner, or the super_admin role) is the feedback "receiver" —
 * triages status/priority/assignment and can delete tickets. A plain admin
 * or any other role that submits feedback can view and comment on their own
 * ticket but cannot touch status/priority/assignment/admin_notes or delete.
 */
class FeedbackTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    private function superAdminRoleUser(): User
    {
        $u = User::factory()->create(['email' => 'superadmin@test.com']);
        $u->assignRole('super_admin');
        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    private function ticket(array $attrs = []): FeedbackTicket
    {
        return FeedbackTicket::create(array_merge([
            'title' => 'Something is broken',
            'status' => 'open',
            'priority' => 'medium',
            'type' => 'bug',
        ], $attrs));
    }

    // ── canManageFeedback() ──────────────────────────────────────────────────

    public function test_can_manage_feedback_true_for_owner_and_super_admin_role_only(): void
    {
        $this->assertTrue($this->owner()->canManageFeedback());
        $this->assertTrue($this->superAdminRoleUser()->canManageFeedback());
        $this->assertFalse($this->admin()->canManageFeedback());

        $streamer = User::factory()->create();
        $streamer->assignRole('streamer');
        $this->assertFalse($streamer->canManageFeedback());
    }

    // ── Resource access ──────────────────────────────────────────────────────

    public function test_any_authenticated_user_can_access_the_resource(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(FeedbackTicketResource::canAccess());

        $streamer = User::factory()->create();
        $streamer->assignRole('streamer');
        $this->actingAs($streamer);
        $this->assertTrue(FeedbackTicketResource::canAccess());
    }

    // ── Query scoping ────────────────────────────────────────────────────────

    public function test_submitter_sees_only_their_own_tickets(): void
    {
        $submitter = $this->admin();
        $other = User::factory()->create();

        $mine  = $this->ticket(['title' => 'Mine', 'submitted_by' => $submitter->id]);
        $theirs = $this->ticket(['title' => 'Theirs', 'submitted_by' => $other->id]);

        $this->actingAs($submitter);
        $ids = FeedbackTicketResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_manager_sees_all_tickets(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $t1 = $this->ticket(['title' => 'A', 'submitted_by' => $a->id]);
        $t2 = $this->ticket(['title' => 'B', 'submitted_by' => $b->id]);

        $this->actingAs($this->owner());
        $ids = FeedbackTicketResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($t1->id, $ids);
        $this->assertContains($t2->id, $ids);
    }

    // ── canView / canEdit / canDelete ───────────────────────────────────────

    public function test_submitter_can_view_own_ticket_but_not_edit_or_delete(): void
    {
        $submitter = $this->admin();
        $t = $this->ticket(['submitted_by' => $submitter->id]);

        $this->actingAs($submitter);

        $this->assertTrue(FeedbackTicketResource::canView($t));
        $this->assertFalse(FeedbackTicketResource::canEdit($t));
        $this->assertFalse(FeedbackTicketResource::canDelete($t));
        $this->assertFalse(FeedbackTicketResource::canDeleteAny());
    }

    public function test_submitter_cannot_view_someone_elses_ticket(): void
    {
        $submitter = $this->admin();
        $other = User::factory()->create();
        $t = $this->ticket(['submitted_by' => $other->id]);

        $this->actingAs($submitter);

        $this->assertFalse(FeedbackTicketResource::canView($t));
    }

    public function test_manager_can_view_edit_and_delete_any_ticket(): void
    {
        $submitter = User::factory()->create();
        $t = $this->ticket(['submitted_by' => $submitter->id]);

        $this->actingAs($this->owner());

        $this->assertTrue(FeedbackTicketResource::canView($t));
        $this->assertTrue(FeedbackTicketResource::canEdit($t));
        $this->assertTrue(FeedbackTicketResource::canDelete($t));
        $this->assertTrue(FeedbackTicketResource::canDeleteAny());
    }

    // ── View page: admin section + status actions hidden for non-managers ───

    public function test_view_page_hides_admin_fields_and_status_actions_for_submitter(): void
    {
        $submitter = $this->admin();
        $t = $this->ticket(['submitted_by' => $submitter->id, 'status' => 'open']);

        Livewire::actingAs($submitter);

        Livewire::test(ViewFeedbackTicket::class, ['record' => $t->getRouteKey()])
            ->assertOk()
            ->assertDontSee('Admin Notes')
            ->assertActionHidden('mark_in_progress')
            ->assertActionHidden('mark_resolved')
            ->assertActionHidden('close_ticket')
            ->assertActionHidden('delete');
    }

    public function test_view_page_shows_admin_fields_and_status_actions_for_manager(): void
    {
        $t = $this->ticket(['status' => 'open']);

        Livewire::actingAs($this->owner());

        Livewire::test(ViewFeedbackTicket::class, ['record' => $t->getRouteKey()])
            ->assertOk()
            ->assertSee('Admin Notes')
            ->assertActionVisible('mark_in_progress')
            ->assertActionVisible('delete');
    }

    // ── Comments ─────────────────────────────────────────────────────────────

    public function test_submitter_can_add_a_comment_on_their_own_ticket(): void
    {
        $submitter = $this->admin();
        $t = $this->ticket(['submitted_by' => $submitter->id]);

        Livewire::actingAs($submitter);

        Livewire::test(CommentsRelationManager::class, [
            'ownerRecord' => $t,
            'pageClass'   => ViewFeedbackTicket::class,
        ])
            ->callTableAction('create', data: ['body' => 'Here is more detail.']);

        $this->assertDatabaseHas('feedback_ticket_comments', [
            'feedback_ticket_id' => $t->id,
            'user_id' => $submitter->id,
            'body' => 'Here is more detail.',
        ]);
    }

    public function test_comment_is_always_attributed_to_the_actual_poster(): void
    {
        // Even though the user_id field no longer appears in the form at all,
        // confirm the create path can't be tricked into posting as someone else.
        $submitter = $this->admin();
        $impersonated = User::factory()->create();
        $t = $this->ticket(['submitted_by' => $submitter->id]);

        Livewire::actingAs($submitter);

        Livewire::test(CommentsRelationManager::class, [
            'ownerRecord' => $t,
            'pageClass'   => ViewFeedbackTicket::class,
        ])
            ->callTableAction('create', data: ['body' => 'Test', 'user_id' => $impersonated->id]);

        $this->assertDatabaseHas('feedback_ticket_comments', [
            'feedback_ticket_id' => $t->id,
            'user_id' => $submitter->id,
        ]);
        $this->assertDatabaseMissing('feedback_ticket_comments', [
            'user_id' => $impersonated->id,
        ]);
    }

    public function test_only_manager_can_delete_a_comment(): void
    {
        $submitter = $this->admin();
        $t = $this->ticket(['submitted_by' => $submitter->id]);
        $comment = $t->comments()->create(['user_id' => $submitter->id, 'body' => 'Note']);

        Livewire::actingAs($submitter);
        Livewire::test(CommentsRelationManager::class, [
            'ownerRecord' => $t,
            'pageClass'   => ViewFeedbackTicket::class,
        ])->assertTableActionHidden('delete', $comment);

        Livewire::actingAs($this->owner());
        Livewire::test(CommentsRelationManager::class, [
            'ownerRecord' => $t,
            'pageClass'   => ViewFeedbackTicket::class,
        ])->assertTableActionVisible('delete', $comment);
    }
}
