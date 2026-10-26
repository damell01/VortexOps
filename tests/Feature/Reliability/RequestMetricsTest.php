<?php
namespace Tests\Feature\Reliability;

use App\Http\Middleware\PerformanceHeaders;
use App\Support\RequestMetrics;
use Illuminate\Http\Request;
use Tests\TestCase;

class RequestMetricsTest extends TestCase
{
    public function test_admin_response_is_private_and_exposes_timings(): void
    {
        $request = Request::create('/admin/reports');
        $response = (new PerformanceHeaders)->handle($request, function () {
            $metrics = app(RequestMetrics::class);
            $metrics->queries = 4;
            $metrics->databaseMs = 12;
            return response('ok');
        });
        $this->assertSame('4', $response->headers->get('X-Query-Count'));
        $this->assertStringContainsString('db;dur=12.0', $response->headers->get('Server-Timing'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertFalse(app(RequestMetrics::class)->active);
    }

    public function test_a_slow_button_press_names_the_page_and_the_button(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        $request = Request::create('/livewire-abc/update', 'POST', ['components' => [[
            'snapshot' => json_encode(['memo' => ['name' => 'app.filament.pages.inventory-scanner']]),
            'calls' => [['method' => 'barcodeScanned', 'params' => ['123']]],
        ]]]);
        $request->headers->set('referer', 'https://example.test/admin/inventory-scanner?mode=lookup');

        (new PerformanceHeaders)->handle($request, function () {
            app(RequestMetrics::class)->queries = 150; // over the slow threshold
            return response('ok');
        });

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'Slow or failed web request'
            && $context['page'] === '/admin/inventory-scanner'
            && $context['calls'] === 'inventory-scanner@barcodeScanned');
    }
}
