<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $httpStatus = 200;

        // ── Database ──────────────────────────────────────────────────────────
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable) {
            $checks['database'] = ['status' => 'fail'];
            $httpStatus = 503;
        }

        // ── Cache ─────────────────────────────────────────────────────────────
        try {
            $probe = 'health:probe:' . getmypid();
            Cache::put($probe, 1, 5);
            Cache::forget($probe);
            $checks['cache'] = ['status' => 'ok'];
        } catch (\Throwable) {
            $checks['cache'] = ['status' => 'fail'];
            $httpStatus = 503;
        }

        // ── Queue ─────────────────────────────────────────────────────────────
        try {
            $pending = DB::table('jobs')->count();
            $failed  = DB::table('failed_jobs')->count();
            $checks['queue'] = [
                'status'  => $failed > 0 ? 'degraded' : 'ok',
                'pending' => $pending,
                'failed'  => $failed,
            ];
        } catch (\Throwable) {
            $checks['queue'] = ['status' => 'unknown'];
        }

        // ── Disk ──────────────────────────────────────────────────────────────
        $freeMb  = (int) round(disk_free_space(storage_path()) / 1048576);
        $totalGb = round(disk_total_space(storage_path()) / 1073741824, 1);
        $diskStatus = match (true) {
            $freeMb < 100  => 'critical',
            $freeMb < 500  => 'warn',
            default        => 'ok',
        };
        $checks['disk'] = ['status' => $diskStatus, 'free_mb' => $freeMb, 'total_gb' => $totalGb];
        if ($diskStatus === 'critical') {
            $httpStatus = 503;
        }

        return response()->json([
            'status'    => $httpStatus === 200 ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ], $httpStatus);
    }
}
