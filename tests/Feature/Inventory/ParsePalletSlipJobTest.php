<?php

namespace Tests\Feature\Inventory;

use App\Jobs\ParsePalletSlipJob;
use App\Models\AiTask;
use App\Models\Pallet;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParsePalletSlipJobTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;
    private AiTask $task;
    private string $fakeImagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $user   = User::factory()->create();
        $vendor = Vendor::create(['name' => 'Test Vendor', 'status' => 'active']);

        $this->pallet = Pallet::create([
            'vendor_id'  => $vendor->id,
            'reference'  => 'PO-999',
            'status'     => 'pending',
            'created_by' => $user->id,
        ]);

        $this->task = AiTask::create([
            'type'          => 'parse_pallet_slip',
            'status'        => 'pending',
            'taskable_type' => Pallet::class,
            'taskable_id'   => $this->pallet->id,
            'triggered_by'  => $user->id,
            'input'         => [],
        ]);

        // Minimal fake image — content doesn't matter since HTTP is faked
        $this->fakeImagePath = sys_get_temp_dir() . '/test_slip_' . uniqid() . '.jpg';
        file_put_contents($this->fakeImagePath, 'fake-image-bytes');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->fakeImagePath)) {
            @unlink($this->fakeImagePath);
        }
        parent::tearDown();
    }

    private function ollamaOk(array $lines): array
    {
        return ['response' => json_encode(['lines' => $lines])];
    }

    private function runJob(?string $path = null): void
    {
        (new ParsePalletSlipJob(
            $this->pallet->id,
            $this->task->id,
            $path ?? $this->fakeImagePath
        ))->handle();
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_handle_marks_task_completed_on_successful_parse(): void
    {
        Http::fake(['*' => Http::response($this->ollamaOk([
            ['description' => 'Topps Chrome Box', 'case_count' => 3, 'unit_cost' => 89.99, 'sku' => 'TCH-001', 'barcode' => null],
        ]))]);

        $this->runJob();

        $this->task->refresh();
        $this->assertEquals('completed', $this->task->status);
        $lines = $this->task->output['lines'];
        $this->assertCount(1, $lines);
        $this->assertEquals('Topps Chrome Box', $lines[0]['description']);
        $this->assertEquals(3, $lines[0]['case_count']);
        $this->assertEquals(89.99, $lines[0]['unit_cost']);
        $this->assertEquals('TCH-001', $lines[0]['sku']);
        $this->assertNull($lines[0]['barcode']);
    }

    public function test_handle_marks_task_as_processing_then_completed(): void
    {
        $statusDuringHandle = null;

        Http::fake(['*' => function () use (&$statusDuringHandle) {
            $statusDuringHandle = AiTask::find($this->task->id)->status;
            return Http::response($this->ollamaOk([
                ['description' => 'Box', 'case_count' => 1, 'unit_cost' => null, 'sku' => null, 'barcode' => null],
            ]));
        }]);

        $this->runJob();

        $this->assertEquals('processing', $statusDuringHandle);
        $this->assertEquals('completed', $this->task->refresh()->status);
    }

    // ── Failure paths ─────────────────────────────────────────────────────────

    public function test_handle_marks_task_failed_when_ollama_returns_http_error(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $this->runJob();

        $this->task->refresh();
        $this->assertEquals('failed', $this->task->status);
        $this->assertStringContainsString('503', $this->task->error_message);
    }

    public function test_handle_marks_task_failed_when_image_file_missing(): void
    {
        $missingPath = sys_get_temp_dir() . '/no_such_file_' . uniqid() . '.jpg';

        Http::fake(['*' => Http::response($this->ollamaOk([]))]);

        $this->runJob($missingPath);

        $this->assertEquals('failed', $this->task->refresh()->status);
    }

    // ── Temp file cleanup ─────────────────────────────────────────────────────

    public function test_handle_deletes_temp_file_after_successful_parse(): void
    {
        Http::fake(['*' => Http::response($this->ollamaOk([
            ['description' => 'Hobby Box', 'case_count' => 1, 'unit_cost' => null, 'sku' => null, 'barcode' => null],
        ]))]);

        $this->runJob();

        $this->assertFileDoesNotExist($this->fakeImagePath);
    }

    public function test_handle_deletes_temp_file_even_on_http_failure(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);

        $this->runJob();

        $this->assertFileDoesNotExist($this->fakeImagePath);
    }

    // ── JSON parsing tolerance ────────────────────────────────────────────────

    public function test_handle_extracts_json_embedded_in_prose(): void
    {
        $prose = 'Sure, here is what I found: {"lines":[{"description":"Prizm Draft","case_count":2,"unit_cost":49.99,"sku":null,"barcode":null}]} Hope that helps!';

        Http::fake(['*' => Http::response(['response' => $prose])]);

        $this->runJob();

        $this->task->refresh();
        $this->assertEquals('completed', $this->task->status);
        $this->assertEquals('Prizm Draft', $this->task->output['lines'][0]['description']);
        $this->assertEquals(2, $this->task->output['lines'][0]['case_count']);
    }

    public function test_handle_returns_empty_lines_when_model_produces_no_json(): void
    {
        Http::fake(['*' => Http::response(['response' => 'I cannot read the image clearly.'])]);

        $this->runJob();

        $this->task->refresh();
        $this->assertEquals('completed', $this->task->status);
        $this->assertEquals([], $this->task->output['lines']);
    }

    public function test_handle_accepts_bare_json_array_from_model(): void
    {
        $arrayJson = json_encode([
            ['description' => 'Box A', 'case_count' => 1, 'unit_cost' => 50.00, 'sku' => null, 'barcode' => null],
            ['description' => 'Box B', 'case_count' => 3, 'unit_cost' => null,  'sku' => null, 'barcode' => null],
        ]);

        Http::fake(['*' => Http::response(['response' => $arrayJson])]);

        $this->runJob();

        $this->task->refresh();
        $lines = $this->task->output['lines'];
        $this->assertCount(2, $lines);
        $this->assertEquals('Box A', $lines[0]['description']);
        $this->assertEquals('Box B', $lines[1]['description']);
    }

    // ── normalizeLines ────────────────────────────────────────────────────────

    public function test_handle_normalizes_zero_case_count_to_one(): void
    {
        Http::fake(['*' => Http::response($this->ollamaOk([
            ['description' => 'Box', 'case_count' => 0,  'unit_cost' => null, 'sku' => null, 'barcode' => null],
            ['description' => 'Bag', 'case_count' => -3, 'unit_cost' => null, 'sku' => null, 'barcode' => null],
        ]))]);

        $this->runJob();

        $lines = $this->task->refresh()->output['lines'];
        $this->assertEquals(1, $lines[0]['case_count']);
        $this->assertEquals(1, $lines[1]['case_count']);
    }

    public function test_handle_skips_lines_with_empty_description(): void
    {
        Http::fake(['*' => Http::response($this->ollamaOk([
            ['description' => '',      'case_count' => 1, 'unit_cost' => null, 'sku' => null, 'barcode' => null],
            ['description' => '   ',   'case_count' => 1, 'unit_cost' => null, 'sku' => null, 'barcode' => null],
            ['description' => 'Valid', 'case_count' => 2, 'unit_cost' => null, 'sku' => null, 'barcode' => null],
        ]))]);

        $this->runJob();

        $lines = $this->task->refresh()->output['lines'];
        $this->assertCount(1, $lines);
        $this->assertEquals('Valid', $lines[0]['description']);
    }

    public function test_handle_accepts_alternative_field_names_from_model(): void
    {
        // Vision models may return 'name' instead of 'description', 'qty'/'quantity' instead of 'case_count'
        $json = json_encode(['lines' => [
            ['name' => 'Chrome Base Set', 'qty' => 4, 'unit_cost' => 12.50, 'sku' => null, 'barcode' => null],
        ]]);

        Http::fake(['*' => Http::response(['response' => $json])]);

        $this->runJob();

        $lines = $this->task->refresh()->output['lines'];
        $this->assertEquals('Chrome Base Set', $lines[0]['description']);
        $this->assertEquals(4, $lines[0]['case_count']);
    }

    public function test_handle_trims_whitespace_from_sku_and_barcode(): void
    {
        Http::fake(['*' => Http::response($this->ollamaOk([
            ['description' => 'Box', 'case_count' => 1, 'unit_cost' => null, 'sku' => '  ABC-123  ', 'barcode' => ' 0123456789 '],
        ]))]);

        $this->runJob();

        $lines = $this->task->refresh()->output['lines'];
        $this->assertEquals('ABC-123', $lines[0]['sku']);
        $this->assertEquals('0123456789', $lines[0]['barcode']);
    }

    public function test_handle_sets_unit_cost_to_null_when_absent(): void
    {
        Http::fake(['*' => Http::response($this->ollamaOk([
            ['description' => 'Box', 'case_count' => 1, 'sku' => null, 'barcode' => null],
        ]))]);

        $this->runJob();

        $lines = $this->task->refresh()->output['lines'];
        $this->assertNull($lines[0]['unit_cost']);
    }

    public function test_handle_processes_multiple_lines_from_single_image(): void
    {
        Http::fake(['*' => Http::response($this->ollamaOk([
            ['description' => 'Product A', 'case_count' => 1, 'unit_cost' => 10.00, 'sku' => 'A1', 'barcode' => null],
            ['description' => 'Product B', 'case_count' => 2, 'unit_cost' => 20.00, 'sku' => 'B2', 'barcode' => null],
            ['description' => 'Product C', 'case_count' => 3, 'unit_cost' => 30.00, 'sku' => 'C3', 'barcode' => null],
        ]))]);

        $this->runJob();

        $this->assertCount(3, $this->task->refresh()->output['lines']);
    }
}
