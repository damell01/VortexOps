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
}
