<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\Filament\Pages\SystemHealth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemHealthAiSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_the_ai_stack_checklist(): void
    {
        $fake = new class implements AIProvider {
            public function name(): string { return 'ollama'; }
            public function chat(array $m, string $model, array $o = []): string { return ''; }
            public function stream(array $m, string $model, array $o = []): \Generator { yield ''; }
            public function vision(string $p, string $b, string $model, array $o = []): string { return ''; }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return ['llama3.2:3b','moondream','nomic-embed-text']; }
            public function isHealthy(): bool { return true; }
        };
        $this->app->instance(AIProvider::class, $fake);

        $owner = User::factory()->create(['email' => config('app.owner_email', 'dbellcreations@gmail.com')]);
        $this->actingAs($owner);

        Livewire::test(SystemHealth::class)
            ->assertOk()
            ->assertSee('AI Stack')
            ->assertSee('Ollama backend')
            ->assertSee('Tools registered');
    }
}
