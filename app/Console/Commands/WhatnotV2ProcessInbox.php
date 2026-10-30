<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Consume authenticated webhook inbox without making unverified operational writes.
 * The inbox remains an independent durable feed; scraper and payroll stay authoritative.
 */
final class WhatnotV2ProcessInbox extends Command
{
    protected $signature = 'whatnot:v2-process-inbox {--limit=100}';
    protected $description = 'Validate and classify signed Whatnot v2 webhook events for reconciliation.';

    public function handle(): int
    {
        if (! app()->environment('production') || ! config('services.whatnot_seller.webhook_processing_enabled', false)) {
            $this->line('Webhook inbox processing disabled.');
            return self::SUCCESS;
        }
        $limit = max(1, min(500, (int) $this->option('limit')));
        $processed = 0;
        $errors = 0;
        $known = ['orders.created', 'orders.updated', 'shows.created', 'shows.updated', 'shows.started', 'shows.ended', 'shows.cancelled'];
        $events = DB::table('whatnot_api_webhook_events')->where('status', 'pending')->orderBy('id')->limit($limit)->get();
        foreach ($events as $event) {
            try {
                DB::transaction(function () use ($event, $known) {
                    $current = DB::table('whatnot_api_webhook_events')->where('id', $event->id)->lockForUpdate()->first();
                    if (! $current || $current->status !== 'pending') {
                        return;
                    }
                    $raw = Crypt::decryptString($current->encrypted_payload);
                    if (! hash_equals($current->payload_sha256, hash('sha256', $raw))) {
                        throw new \RuntimeException('Event integrity mismatch.');
                    }
                    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    if (! is_array($payload) || ($payload['version'] ?? null) !== 'v2'
                        || ($payload['seller_id'] ?? null) !== $current->seller_id
                        || ($payload['type'] ?? null) !== $current->topic
                        || ! is_array($payload['data'] ?? null)) {
                        throw new \RuntimeException('Invalid stored v2 envelope.');
                    }
                    $status = in_array($current->topic, $known, true) ? 'staged' : 'ignored';
                    if (str_starts_with($current->topic, 'orders.') && $status === 'staged') {
                        // Fail closed on malformed v2 order money or identity fields.
                        app(\\App\\Services\\WhatnotV2OrderMapper::class)->map($payload['data']);
                    }
                    DB::table('whatnot_api_webhook_events')->where('id', $current->id)->update([
                        'status' => $status, 'processed_at' => now(), 'updated_at' => now(),
                    ]);
                });
                $processed++;
            } catch (Throwable $e) {
                $errors++;
                report($e);
                DB::table('whatnot_api_webhook_events')->where('id', $event->id)->update([
                    'status' => 'failed', 'last_error' => get_class($e), 'updated_at' => now(),
                ]);
            }
        }
        $this->info("Classified {$processed} events; {$errors} failed. Operational writes remain disabled.");
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
