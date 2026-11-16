<?php
namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\GuardChatifyPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChatMessagingRolloutTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_chat_rollout_is_disabled_by_default(): void
    {
        config(['chatify-preview.team_enabled' => false]);
        $this->assertFalse((bool) config('chatify-preview.team_enabled'));
    }

    public function test_owner_preview_remains_separate_from_employee_chat_rollout(): void
    {
        config(['chatify-preview.team_enabled' => true, 'chatify-preview.enabled' => false]);
        $this->assertFalse((bool) config('chatify-preview.enabled'));
        $this->assertTrue((bool) config('chatify-preview.team_enabled'));
    }

    public function test_team_rollout_routes_allow_signed_in_teammates_when_explicitly_enabled(): void
    {
        $teammate = User::factory()->create();
        $this->actingAs($teammate);
        config([
            'chatify-preview.available' => true,
            'chatify-preview.enabled' => false,
            'chatify-preview.team_enabled' => true,
        ]);

        $guard = new GuardChatifyPreview();
        $request = Request::create('/api/chatify/v1/conversations', 'GET');
        $response = $guard->handle($request, fn () => response('allowed'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_owner_only_preview_rejects_other_accounts_at_the_package_route(): void
    {
        $teammate = User::factory()->create();
        $this->actingAs($teammate);
        config([
            'chatify-preview.available' => true,
            'chatify-preview.enabled' => true,
            'chatify-preview.team_enabled' => false,
        ]);

        $this->expectException(HttpException::class);
        (new GuardChatifyPreview())->handle(
            Request::create('/api/chatify/v1/conversations', 'GET'),
            fn () => response('allowed')
        );
    }
}
