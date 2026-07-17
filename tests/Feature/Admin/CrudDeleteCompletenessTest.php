<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Models\DeductionRequest;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Row-level delete was added to several resource tables that already had
 * (or should have had) the underlying canDelete()/canDeleteAny() logic but
 * no UI to actually use it — most notably Users. These tests cover the
 * protections that logic enforces.
 */
class CrudDeleteCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        foreach (['admin', 'super_admin', 'streamer', 'fulfillment', 'fulfillment_admin'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    // ── UserResource ─────────────────────────────────────────────────────────

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->assertFalse(UserResource::canDelete($admin));
    }

    public function test_admin_cannot_delete_the_owner_account(): void
    {
        $owner = $this->owner();
        $this->actingAs($this->admin());

        $this->assertFalse(UserResource::canDelete($owner));
    }

    public function test_admin_cannot_delete_another_privileged_user(): void
    {
        $otherAdmin = User::factory()->create(['email' => 'other-admin@test.com']);
        $otherAdmin->assignRole('admin');

        $this->actingAs($this->admin());

        $this->assertFalse(UserResource::canDelete($otherAdmin));
    }

    public function test_owner_can_delete_a_privileged_user(): void
    {
        $otherAdmin = User::factory()->create(['email' => 'other-admin@test.com']);
        $otherAdmin->assignRole('admin');

        $this->actingAs($this->owner());

        $this->assertTrue(UserResource::canDelete($otherAdmin));
    }

    public function test_admin_can_delete_a_plain_non_privileged_user(): void
    {
        $streamerUser = User::factory()->create();
        $streamerUser->assignRole('streamer');

        $this->actingAs($this->admin());

        $this->assertTrue(UserResource::canDelete($streamerUser));
    }

    // ── RoleResource ─────────────────────────────────────────────────────────

    public function test_core_roles_are_protected_from_deletion(): void
    {
        foreach (RoleResource::CORE_ROLES as $name) {
            $this->assertTrue(RoleResource::isCoreRole($name));
        }
        $this->assertFalse(RoleResource::isCoreRole('seasonal-helper'));
    }

    // ── DeductionRequestResource ────────────────────────────────────────────

    private function deductionRequest(string $status): DeductionRequest
    {
        $creator = User::factory()->create();
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);

        return DeductionRequest::create([
            'show_id' => $show->id,
            'streamer_id' => $streamer->id,
            'status' => $status,
        ]);
    }

    public function test_pending_deduction_request_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = $this->deductionRequest('pending');

        $this->assertTrue(DeductionRequestResource::canDelete($request));
    }

    public function test_rejected_deduction_request_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = $this->deductionRequest('rejected');

        $this->assertTrue(DeductionRequestResource::canDelete($request));
    }

    public function test_approved_deduction_request_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = $this->deductionRequest('approved');

        $this->assertFalse(DeductionRequestResource::canDelete($request));
    }

    public function test_processed_deduction_request_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = $this->deductionRequest('processed');

        $this->assertFalse(DeductionRequestResource::canDelete($request));
    }
}
