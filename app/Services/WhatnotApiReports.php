<?php

namespace App\Services;

use App\Models\WhatnotApiConnection;
use Illuminate\Support\Carbon;

/**
 * Generate a Seller API report (POST /reports), wait for the run to complete
 * (GET /reports/{id}) and download its CSV from report_url. Whatnot returns
 * the same run for an identical request already in flight or recently done,
 * so a report still generating is picked up by the next attempt.
 */
final class WhatnotApiReports
{
    private const POLL_SECONDS = 5;

    public function __construct(private readonly WhatnotSellerApiClient $client) {}

    public static function iso(Carbon $at): string
    {
        return $at->copy()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /** Financial report parameters (transactions completed in [start, end)). */
    public static function financial(Carbon $start, Carbon $end): array
    {
        return ['report_type' => 'seller_financial_report', 'start_date' => self::iso($start), 'end_date' => self::iso($end)];
    }

    /** Overview analytics parameters (one row per day). */
    public static function overview(Carbon $start, Carbon $end): array
    {
        return ['report_type' => 'analytics_overview_report', 'start_at' => self::iso($start), 'end_at' => self::iso($end)];
    }

    /**
     * @return array{status:string, csv?:string, report_id?:?string, http_status?:int}
     */
    public function fetch(WhatnotApiConnection $connection, array $parameters, int $maxPolls = 12): array
    {
        $response = $this->client->call($connection, 'generate_report', body: ['report_parameters' => $parameters]);
        if ($response->status() === 403) {
            return ['status' => 'needs_permission', 'http_status' => 403];
        }
        if (! $response->successful()) {
            return ['status' => 'request_failed', 'http_status' => $response->status()];
        }
        $run = (array) $response->json();
        for ($i = 0; ($run['status'] ?? null) !== 'completed' && $i < $maxPolls; $i++) {
            if (in_array($run['status'] ?? null, ['failed', 'cancelled'], true)) {
                return ['status' => 'report_'.$run['status'], 'report_id' => $run['id'] ?? null];
            }
            $this->client->pause(self::POLL_SECONDS);
            $poll = $this->client->call($connection, 'report', (string) ($run['id'] ?? ''));
            if (! $poll->successful()) {
                return ['status' => 'poll_failed', 'http_status' => $poll->status(), 'report_id' => $run['id'] ?? null];
            }
            $run = (array) $poll->json();
        }
        if (($run['status'] ?? null) !== 'completed' || ! is_string($run['report_url'] ?? null)) {
            return ['status' => 'still_generating', 'report_id' => $run['id'] ?? null];
        }

        return ['status' => 'completed', 'csv' => $this->client->downloadReport($run['report_url']), 'report_id' => $run['id'] ?? null];
    }
}
