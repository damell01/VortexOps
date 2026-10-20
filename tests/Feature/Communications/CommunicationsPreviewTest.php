<?php

namespace Tests\Feature\Communications;

use App\Filament\Pages\Communications;
use App\Filament\Resources\RoleResource;
use App\Livewire\AnnouncementPreview;
use App\Models\TeamAnnouncement;
use App\Models\TeamConversation;
use App\Models\TeamMessage;
use App\Models\User;
use App\Services\TeamCommunications;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunicationsPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $person;
    private TeamCommunications $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->enableAdminModules();
        config(['app.owner_email' => 'owner@example.test', 'app.display_timezone' => 'America/Chicago']);
        Carbon::setTestNow(Carbon::parse('2026-10-20 16:00:00', 'UTC'));
        $this->owner = User::factory()->create(['email' => 'owner@example.test']);
        $this->person = User::factory()->create(['name' => 'Preview Recipient']);
        $this->service = app(TeamCommunications::class);
        $this->actingAs($this->owner);
        Notification::fake(); Mail::fake(); Queue::fake();
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function draft(array $overrides = []): TeamAnnouncement
    {
        return $this->service->saveAnnouncement($this->owner, $overrides + [
            'title' => 'Team update', 'body' => 'A private preview notice', 'tone' => 'info', 'audience' => 'all', 'role_ids' => [], 'user_ids' => [],
            'show_banner' => true, 'email_requested' => true, 'push_requested' => true, 'starts_at' => '2026-10-20T10:00', 'ends_at' => '2026-10-20T12:00',
        ]);
    }

    public function test_owner_can_open_workspace_but_admins_super_admins_streamers_and_roleless_users_cannot(): void
    {
        Livewire::test(Communications::class)->assertSee('Owner preview')->assertSee('Messages')->assertSee('Announcements');
        $this->assertArrayNotHasKey(Communications::class, RoleResource::pageOptions());
        foreach (['admin', 'super_admin', 'streamer', null] as $role) {
            $user = User::factory()->create();
            if ($role) $user->assignRole(Role::findOrCreate($role, 'web'));
            $this->actingAs($user);
            $this->assertFalse(Communications::canAccess());
            Livewire::test(Communications::class)->assertForbidden();
            Livewire::test(AnnouncementPreview::class)->assertForbidden();
            $this->get(Communications::getUrl(panel: 'admin'))->assertForbidden();
        }
    }

    public function test_preview_gates_recheck_access_on_livewire_updates(): void
    {
        $page = Livewire::test(Communications::class);
        $this->actingAs($this->person);
        $page->call('changeTab', 'announcements')->assertForbidden();
    }

    public function test_direct_chats_are_reused_and_group_members_are_stored_without_notifications(): void
    {
        $chat = $this->service->start($this->owner, ['kind' => 'direct', 'title' => null, 'members' => [$this->person->id]]);
        $again = $this->service->start($this->owner, ['kind' => 'direct', 'members' => [$this->person->id]]);
        $this->assertSame($chat->id, $again->id);
        $group = $this->service->start($this->owner, ['kind' => 'group', 'title' => 'Stream team', 'members' => [$this->person->id]]);
        $this->assertSame('Stream team', $group->title);
        $this->assertCount(2, $group->members);
        $this->assertTrue($chat->is_preview);
        $this->assertTrue($group->is_preview);
        Notification::assertNothingSent(); Mail::assertNothingOutgoing(); Queue::assertNothingPushed();
    }

    public function test_messages_are_member_scoped_idempotent_escaped_and_private(): void
    {
        $chat = $this->service->start($this->owner, ['kind' => 'direct', 'members' => [$this->person->id]]);
        $key = (string) Str::uuid();
        $body = '<script>alert("unsafe")</script>';
        $a = $this->service->send($this->owner, $chat->id, $body, $key);
        $b = $this->service->send($this->owner, $chat->id, $body, $key);
        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('team_messages', 1);
        $this->assertDatabaseHas('team_conversation_members', ['conversation_id' => $chat->id, 'user_id' => $this->owner->id, 'last_read_message_id' => $a->id]);
        Livewire::test(Communications::class)->call('selectConversation', $chat->id)->assertSee($body)->assertDontSeeHtml($body);
        $this->actingAs($this->person);
        Livewire::test(Communications::class)->assertForbidden();
        Notification::assertNothingSent(); Mail::assertNothingOutgoing(); Queue::assertNothingPushed();
    }

    public function test_even_owner_cannot_open_or_post_to_a_conversation_they_do_not_belong_to(): void
    {
        $chat = TeamConversation::create(['kind' => 'group', 'title' => 'Other conversation', 'is_preview' => true, 'created_by' => $this->person->id]);
        $chat->members()->attach($this->person->id);
        Livewire::test(Communications::class)->assertDontSee('Other conversation');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->send($this->owner, $chat->id, 'Cannot post here', (string) Str::uuid());
    }

    public function test_new_direct_chat_and_composer_work_in_livewire(): void
    {
        Livewire::test(Communications::class)->set('directMember', (string) $this->person->id)->call('startChat')
            ->set('body', 'Testing the owner-only inbox')->call('sendMessage')->assertSet('body', '')->assertSee('Testing the owner-only inbox');
        $this->assertDatabaseCount('team_messages', 1);
        Notification::assertNothingSent(); Mail::assertNothingOutgoing(); Queue::assertNothingPushed();
    }

    public function test_announcements_resolve_everyone_roles_or_selected_users_without_delivery(): void
    {
        $streamer = Role::findOrCreate('streamer', 'web');
        $this->person->assignRole($streamer);
        $everyone = $this->draft();
        $roles = $this->draft(['audience' => 'roles', 'role_ids' => [$streamer->id]]);
        $users = $this->draft(['audience' => 'users', 'user_ids' => [$this->person->id]]);
        $this->assertSame(2, $this->service->recipientCount($this->owner, $everyone));
        $this->assertSame(1, $this->service->recipientCount($this->owner, $roles));
        $this->assertSame(1, $this->service->recipientCount($this->owner, $users));
        foreach ([$everyone, $roles, $users] as $a) $this->service->publishPreview($this->owner, $a->id);
        Notification::assertNothingSent(); Mail::assertNothingOutgoing(); Queue::assertNothingPushed();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_banner_start_end_draft_archive_and_owner_preview_are_enforced_in_central_time(): void
    {
        $a = $this->draft();
        $this->assertSame('2026-10-20 15:00:00', $a->starts_at->format('Y-m-d H:i:s'));
        $this->assertCount(0, $this->service->banners($this->owner));
        $this->service->publishPreview($this->owner, $a->id);
        Livewire::test(AnnouncementPreview::class)->assertSee('Team update')->assertSee('Owner preview');
        Carbon::setTestNow(Carbon::parse('2026-10-20 17:00:00', 'UTC'));
        $this->assertCount(0, $this->service->banners($this->owner));
        Carbon::setTestNow(Carbon::parse('2026-10-20 14:59:59', 'UTC'));
        $this->assertCount(0, $this->service->banners($this->owner));
        Carbon::setTestNow(Carbon::parse('2026-10-20 15:00:00', 'UTC'));
        $this->assertCount(1, $this->service->banners($this->owner));
        $this->service->archive($this->owner, $a->id);
        $this->assertCount(0, $this->service->banners($this->owner));
    }

    public function test_announcement_editor_round_trips_dates_and_edits_return_to_draft(): void
    {
        $a = $this->draft();
        $this->service->publishPreview($this->owner, $a->id);
        Livewire::test(Communications::class)->call('changeTab', 'announcements')->call('editAnnouncement', $a->id)
            ->assertSet('announcement.starts_at', '2026-10-20T10:00')->assertSet('announcement.ends_at', '2026-10-20T12:00')
            ->set('announcement.title', 'Edited update')->call('saveAnnouncement')->assertHasNoErrors()->assertSee('Edited update');
        $this->assertSame('draft', $a->fresh()->status);
        $this->assertCount(0, $this->service->banners($this->owner));
    }

    public function test_invalid_schedules_and_empty_targeted_audiences_cannot_be_saved(): void
    {
        foreach ([['ends_at' => '2026-10-20T09:00'], ['audience' => 'roles', 'role_ids' => []], ['audience' => 'users', 'user_ids' => []]] as $data) {
            try { $this->draft($data); $this->fail('Invalid announcement accepted'); }
            catch (ValidationException) { $this->assertDatabaseCount('team_announcements', 0); }
        }
    }

    public function test_message_history_is_paginated_and_blank_messages_are_rejected(): void
    {
        $chat = $this->service->start($this->owner, ['kind' => 'direct', 'members' => [$this->person->id]]);
        for ($i = 0; $i < 42; $i++) $this->service->send($this->owner, $chat->id, sprintf('History-%03d-END', $i), (string) Str::uuid());
        Livewire::test(Communications::class)->call('selectConversation', $chat->id)
            ->assertSee('History-041-END')->assertDontSee('History-000-END')
            ->call('paginateSection', 'messagePage', 2)->assertSee('History-000-END')->assertSee('History-001-END')->assertDontSee('History-002-END')
            ->set('body', '   ')->call('sendMessage')->assertHasErrors('body');
        $this->assertDatabaseCount('team_messages', 42);
    }

    public function test_expired_announcements_cannot_be_activated_and_live_records_are_not_exposed_in_preview(): void
    {
        $this->draft(['title' => 'Live-only announcement'])->update(['is_preview' => false]);
        Livewire::test(Communications::class)->call('changeTab', 'announcements')->assertDontSee('Live-only announcement');
        $a = $this->draft(['ends_at' => '2026-10-20T10:30']);
        $this->expectException(ValidationException::class);
        $this->service->publishPreview($this->owner, $a->id);
    }

    public function test_inbox_search_groups_and_unread_counts_use_membership_and_read_markers(): void
    {
        $direct = $this->service->start($this->owner, ['kind' => 'direct', 'members' => [$this->person->id]]);
        $group = $this->service->start($this->owner, ['kind' => 'group', 'title' => 'Inventory crew', 'members' => [$this->person->id]]);
        foreach (['Needle one', 'Needle two'] as $body) $direct->messages()->create(['sender_id' => $this->person->id, 'body' => $body, 'client_key' => (string) Str::uuid()]);
        $this->assertSame(2, $this->service->inbox($this->owner)->find($direct->id)->unread_count);
        $this->assertSame([$direct->id], $this->service->inbox($this->owner, 'Needle')->pluck('id')->all());
        $this->assertSame([$direct->id], $this->service->inbox($this->owner, '', 'unread')->pluck('id')->all());
        $this->assertSame([$group->id], $this->service->inbox($this->owner, '', 'groups')->pluck('id')->all());
        Livewire::test(Communications::class)->assertSeeHtml('aria-label="2 unread messages"')->call('selectConversation', $direct->id)->assertDontSeeHtml('aria-label="2 unread messages"')
            ->set('messageSearch', 'Needle one')->assertSee('Needle one');
        $this->assertSame(0, $this->service->inbox($this->owner, '', 'unread')->count());
        $this->actingAs($this->person);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->inbox($this->person, 'Needle')->get();
    }

    public function test_attachments_can_be_saved_without_text_and_downloads_require_owner_and_membership(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $chat = $this->service->start($this->owner, ['kind' => 'direct', 'members' => [$this->person->id]]);
        $upload = \Illuminate\Http\UploadedFile::fake()->create('payroll-review.pdf', 20, 'application/pdf');
        $key = (string) Str::uuid();
        $message = $this->service->send($this->owner, $chat->id, '', $key, [$upload]);
        $file = $message->attachments()->firstOrFail();
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($file->path);
        $this->assertSame('payroll-review.pdf', $file->name);
        $this->service->send($this->owner, $chat->id, '', $key, [$upload]);
        $this->assertDatabaseCount('team_message_attachments', 1);
        $this->get(route('communications.attachment', $file->id))->assertOk()->assertDownload('payroll-review.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->person);
        $this->get(route('communications.attachment', $file->id))->assertForbidden();
        $this->actingAs($this->owner);
        $chat->members()->detach($this->owner->id);
        $this->get(route('communications.attachment', $file->id))->assertNotFound();
        Notification::assertNothingSent(); Mail::assertNothingOutgoing(); Queue::assertNothingPushed();
    }

    public function test_attachment_validation_rejects_executable_types_oversized_files_and_too_many_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $chat = $this->service->start($this->owner, ['kind' => 'direct', 'members' => [$this->person->id]]);
        foreach ([
            [\Illuminate\Http\UploadedFile::fake()->create('attack.html', 1, 'text/html')],
            [\Illuminate\Http\UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')],
            array_fill(0, 4, \Illuminate\Http\UploadedFile::fake()->create('notes.txt', 1, 'text/plain')),
        ] as $files) {
            try { $this->service->send($this->owner, $chat->id, 'Files', (string) Str::uuid(), $files); $this->fail('Unsafe upload accepted'); }
            catch (ValidationException) { $this->assertDatabaseCount('team_messages', 0); }
        }
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('local')->allFiles());
    }

    public function test_livewire_can_attach_and_remove_a_file_before_sending(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $chat = $this->service->start($this->owner, ['kind' => 'direct', 'members' => [$this->person->id]]);
        $file = \Illuminate\Http\UploadedFile::fake()->create('notes.txt', 2, 'text/plain');
        Livewire::test(Communications::class)->call('selectConversation', $chat->id)->set('attachments', [$file])->assertHasNoErrors()
            ->call('removeAttachment', 0)->assertSet('attachments', [])->set('attachments', [$file])->call('sendMessage')->assertHasNoErrors()->assertSee('notes.txt')->assertSet('attachments', []);
        $this->assertDatabaseCount('team_message_attachments', 1);
    }

    public function test_announcement_filters_preview_activation_and_dismissal_are_persisted(): void
    {
        $a = $this->draft(['title' => 'Scheduled message', 'starts_at' => '2026-10-20T11:30', 'ends_at' => '2026-10-20T12:30']);
        $this->service->publishPreview($this->owner, $a->id);
        $active = $this->draft(['title' => 'Active message', 'dismissible' => true, 'in_app_requested' => false]);
        $this->service->publishPreview($this->owner, $active->id);
        Livewire::test(Communications::class)->call('changeTab', 'announcements')->set('announcementFilter', 'scheduled')->assertSee('Scheduled message')->assertDontSee('Active message');
        Livewire::test(AnnouncementPreview::class)->assertSee('Active message')->call('dismiss', $active->id)->assertDontSee('Active message');
        $this->service->publishPreview($this->owner, $active->id);
        Livewire::test(AnnouncementPreview::class)->assertSee('Active message');
        Livewire::test(Communications::class)->call('editAnnouncement', $active->id)->assertSet('announcement.dismissible', true)->assertSet('announcement.in_app_requested', false)
            ->set('announcement.title', 'Preview from editor')->call('saveAnnouncement', true)->assertHasNoErrors();
        $this->assertSame('published', $active->fresh()->status);
        Notification::assertNothingSent(); Mail::assertNothingOutgoing(); Queue::assertNothingPushed();
    }

    public function test_non_dismissible_banner_rejects_dismissal(): void
    {
        $a = $this->draft(['dismissible' => false]);
        $this->service->publishPreview($this->owner, $a->id);
        Livewire::test(AnnouncementPreview::class)->call('dismiss', $a->id)->assertForbidden();
    }

    public function test_preview_banner_is_not_rendered_or_queried_on_non_owner_dashboard(): void
    {
        $a = $this->draft(['title' => 'Hidden preview banner']);
        $this->service->publishPreview($this->owner, $a->id);
        $this->get(\App\Filament\Pages\DashboardImproved::getUrl(panel: 'admin'))->assertOk()->assertSee('Hidden preview banner')->assertSee('Messages &amp; Announcements', false);
        $this->person->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->person);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->get(\App\Filament\Pages\DashboardImproved::getUrl(panel: 'admin'))->assertOk()->assertDontSee('Hidden preview banner')->assertDontSee('Messages &amp; Announcements', false);
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->assertFalse(collect($queries)->contains(fn ($q) => str_contains($q['query'], 'team_announcements')));
    }
}
