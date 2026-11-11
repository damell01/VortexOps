<?php

namespace Tests\Feature;

use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Messages widget: seen + typing for participants only, and link cards for records the viewer may open. */
class ChatPresenceAndLinksTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(User ...$users): string
    {
        $id = (string) Str::uuid();
        DB::table('ch_conversations')->insert(['id' => $id, 'type' => 'direct', 'created_by' => $users[0]->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($users as $u) DB::table('ch_conversation_participants')->insert(['id' => (string) Str::uuid(), 'conversation_id' => $id, 'user_id' => $u->id, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['chatify-preview.available' => true, 'chatify-preview.team_enabled' => true]);
    }

    public function test_participants_see_read_times_and_who_is_typing(): void
    {
        $me = User::factory()->create(['name' => 'Dee Owner']);
        $jordan = User::factory()->create(['name' => 'Jordan Smith']);
        $id = $this->conversation($me, $jordan);
        DB::table('ch_conversation_participants')->where('conversation_id', $id)->where('user_id', $jordan->id)->update(['last_read_at' => now()]);

        $this->actingAs($jordan)->postJson("/admin/communications/conversations/{$id}/presence", ['typing' => true])->assertOk();

        $data = $this->actingAs($me)->getJson("/admin/communications/conversations/{$id}/presence")->assertOk()->json();
        $this->assertSame(['Jordan'], $data['typing']);
        $this->assertSame((string) $jordan->id, $data['read'][0]['user_id']);
        $this->assertNotNull($data['read'][0]['last_read_at']);

        $this->actingAs($jordan)->postJson("/admin/communications/conversations/{$id}/presence", ['typing' => false])->assertOk();
        $this->assertSame([], $this->actingAs($me)->getJson("/admin/communications/conversations/{$id}/presence")->json('typing'));
    }

    public function test_someone_outside_the_conversation_gets_nothing(): void
    {
        $id = $this->conversation(User::factory()->create(), User::factory()->create());
        $this->actingAs(User::factory()->create())->getJson("/admin/communications/conversations/{$id}/presence")->assertNotFound();
    }

    public function test_a_show_link_becomes_a_card_and_other_links_do_not(): void
    {
        $this->enableAdminModules();
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $show = Show::create(['title' => 'Friday Mega Break', 'show_date' => '2026-11-06', 'status' => 'reconciled']);
        $url = url('/admin/shows/' . $show->id);

        $cards = $this->actingAs($owner)->postJson('/admin/communications/link-previews', ['urls' => [$url, 'https://example.com/admin/shows/1', url('/admin/shows/999999')]])
            ->assertOk()->json('cards');

        $this->assertSame(['Show'], array_column($cards, 'kind'));
        $this->assertSame('Friday Mega Break', $cards[$url]['title']);
    }
}
