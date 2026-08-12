<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\Services\AI\Documents\PalletSlipParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalletSlipParserTest extends TestCase
{
    use RefreshDatabase;

    /** Bind a vision provider returning a fixed extraction, recording its inputs. */
    private function visionProvider(string $json): object
    {
        $fake = new class($json) implements AIProvider {
            public array $lastVision = [];
            public function __construct(private string $json) {}
            public function name(): string { return 'fake'; }
            public function chat(array $m, string $model, array $o = []): string { return ''; }
            public function stream(array $m, string $model, array $o = []): \Generator { yield ''; }
            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string
            {
                $this->lastVision = compact('prompt', 'model');
                return $this->json;
            }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return true; }
        };
        $this->app->instance(AIProvider::class, $fake);

        return $fake;
    }

    private function tempImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'slip') . '.jpg';
        file_put_contents($path, 'fake-image-bytes');

        return $path;
    }

    public function test_parses_and_normalizes_lines_via_the_gateway(): void
    {
        $fake = $this->visionProvider(
            '{"lines":[{"description":"2024 Prizm Basketball","case_count":2,"unit_cost":89.99,"sku":"PRZ-1","barcode":"012345678901"}]}'
        );
        \App\Models\Setting::set('ollama_vision_model', 'moondream');

        $path  = $this->tempImage();
        $lines = app(PalletSlipParser::class)->parse($path);

        $this->assertCount(1, $lines);
        $this->assertSame('2024 Prizm Basketball', $lines[0]['description']);
        $this->assertSame(2, $lines[0]['case_count']);
        $this->assertSame(89.99, $lines[0]['unit_cost']);
        // Routed the configured vision model.
        $this->assertSame('moondream', $fake->lastVision['model']);
        // The file is cleaned up after parsing.
        $this->assertFileDoesNotExist($path);
    }

    public function test_defaults_missing_fields_and_skips_blank_descriptions(): void
    {
        $this->visionProvider('{"lines":[{"description":"Box A"},{"description":""},{"name":"Box C","qty":3}]}');

        $lines = app(PalletSlipParser::class)->parse($this->tempImage());

        $this->assertCount(2, $lines);                 // blank description dropped
        $this->assertSame(1, $lines[0]['case_count']); // default qty
        $this->assertNull($lines[0]['unit_cost']);
        $this->assertSame('Box C', $lines[1]['description']);
        $this->assertSame(3, $lines[1]['case_count']);
    }

    public function test_vision_failure_raises(): void
    {
        $throwing = new class implements AIProvider {
            public function name(): string { return 'boom'; }
            public function chat(array $m, string $model, array $o = []): string { return ''; }
            public function stream(array $m, string $model, array $o = []): \Generator { yield ''; }
            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string
            {
                throw new \RuntimeException('vision model not pulled');
            }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return false; }
        };
        $this->app->instance(AIProvider::class, $throwing);

        $this->expectException(\RuntimeException::class);
        app(PalletSlipParser::class)->parse($this->tempImage());
    }
}
