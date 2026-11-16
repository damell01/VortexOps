<?php

namespace Tests\Feature\Communications;

use App\Models\Chat\MemoUserBlock;
use App\Models\Chat\MemoUserSetting;
use App\Models\User;
use Chatify\Services\BlockService;
use Chatify\Services\ConversationService;
use Chatify\Services\UserSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Listing conversations used to run two block lookups and a settings lookup
 * per person per conversation: 400+ queries for a dozen chats, on every open,
 * every send and every 15 seconds. These keep it flat and still correct.
 */
class MessengerSpeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(\Chatify\Models\Conversation::class)) {
            $this->markTestSkipped('Chatify is not installed');
        }
        config(['chatify-preview.available' => true, 'chatify-preview.enabled' => true]);
    }

    public function test_the_inbox_query_count_does_not_grow_per_person(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($owner);
        $conversations = app(ConversationService::class);

        $count = function () {
            MemoUserBlock::forget();
            MemoUserSetting::forget();
            $n = 0;
            DB::listen(function () use (&$n) { $n++; });
            $this->getJson('/api/chatify/v1/conversations?per_page=50')->assertOk();

            return $n;
        };

        foreach (User::factory()->count(3)->create() as $other) $conversations->findOrCreateDirect($owner, $other);
        $few = $count();
        foreach (User::factory()->count(9)->create() as $other) $conversations->findOrCreateDirect($owner, $other);
        $many = $count();

        // Was ~30 extra queries per conversation; now only Chatify's own few per row remain.
        $this->assertLessThan(10 * 9, $many - $few, "9 more conversations cost {$many} - {$few} queries");
    }

    public function test_block_and_settings_answers_stay_correct(): void
    {
        $this->assertSame(MemoUserBlock::class, config('chatify.models.block'));
        $this->assertSame(MemoUserSetting::class, config('chatify.models.user_setting'));
        [$a, $b] = User::factory()->count(2)->create()->all();
        $blocks = app(BlockService::class);

        $this->assertFalse($blocks->isBlocked($a, $b));
        $blocks->block($a, $b);
        $this->assertTrue($blocks->isBlocked($a, $b), 'a new block is seen at once');
        $this->assertFalse($blocks->isBlocked($b, $a));
        $blocks->unblock($a, $b);
        $this->assertFalse($blocks->isBlocked($a, $b), 'an unblock is seen at once');

        $settings = app(UserSettingsService::class);
        $first = $settings->forUser($a);
        $this->assertSame($first->getKey(), $settings->forUser($a)->getKey(), 'one settings row per person');
        $this->assertNotSame($first->getKey(), $settings->forUser($b)->getKey());
    }
}
