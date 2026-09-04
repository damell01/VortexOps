<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowIngestionLog extends Model
{
    protected $fillable = [
        'show_id',
        'whatnot_channel_id',
        'source',
        'status',
        'raw_payload',
        'error_message',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

    public static function statusLabels(): array
    {
        return [
            'success' => 'Success',
            'failed'  => 'Failed',
            'partial' => 'Partial',
        ];
    }

    public static function sourceLabels(): array
    {
        return [
            'whatnot'                         => 'Manual import',
            'whatnot_show_index'              => 'Legacy show index',
            'whatnot_spa_enrichment'          => 'Legacy analytics enrichment',
            'whatnot_recent_refresh'          => 'Legacy recent refresh',
            'whatnot_show_analytics'           => 'Shows + Analytics',
            'whatnot_orders'                   => 'Recent Orders',
            'whatnot_shipments'                => 'Shipments',
            'whatnot_ledger'                   => 'Rolling Ledger',
            'whatnot_nightly_reconciliation'   => 'Nightly Reconciliation',
            'whatnot_deep_backfill'            => 'Deep Backfill',
        ];
    }

    public function sourceLabel(): string
    {
        return static::sourceLabels()[$this->source] ?? $this->source;
    }

    public static function failureTypeLabels(): array
    {
        return [
            'browser_startup' => 'Browser startup failed',
            'browser_lock'    => 'Browser busy / lock timeout',
            'channel_switch'  => 'Channel switch failed',
            'cloudflare'      => 'Cloudflare blocked navigation',
            'auth_session'    => 'Authentication/session issue',
            'no_shows'        => 'No shows returned',
            'show_import'     => 'Show import failed',
            'scraper'         => 'Scraper failed',
        ];
    }

    public function failureType(): ?string
    {
        if ($this->status !== 'failed') {
            return null;
        }

        $error = strtolower((string) $this->error_message);

        return match (true) {
            str_contains($error, 'browser_lock_timeout'),
            str_contains($error, 'browser lock timeout') => 'browser_lock',

            str_contains($error, 'chromium exited before devtools listener was ready'),
            str_contains($error, 'chromium startup failed'),
            str_contains($error, 'died during startup'),
            str_contains($error, 'sigtrap') => 'browser_startup',

            str_contains($error, 'channel_switch_'),
            str_contains($error, 'channel_context_mismatch'),
            str_contains($error, 'channel switch') => 'channel_switch',

            str_contains($error, 'cloudflare'),
            str_contains($error, 'security verification'),
            str_contains($error, '__cf_chl_'),
            str_contains($error, '403 get /dashboard') => 'cloudflare',

            str_contains($error, 'login'),
            str_contains($error, 'authentication'),
            str_contains($error, 'session expired'),
            str_contains($error, 'not authenticated') => 'auth_session',

            str_contains($error, 'zero shows'),
            str_contains($error, 'no shows'),
            str_contains($error, 'returned 0 shows') => 'no_shows',

            $this->show_id !== null => 'show_import',
            default => 'scraper',
        };
    }

    public function failureTypeLabel(): ?string
    {
        $type = $this->failureType();
        return $type ? (static::failureTypeLabels()[$type] ?? $type) : null;
    }

    public function summary(): string
    {
        if ($this->status === 'failed') {
            return $this->failureTypeLabel() ?? ($this->show_id ? 'Show import failed' : $this->sourceLabel() . ' failed');
        }

        if ($this->status === 'partial') {
            return $this->sourceLabel() . ' partially completed';
        }

        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];

        return match ($this->source) {
            'whatnot_show_analytics' => sprintf('Shows + analytics completed%s', isset($payload['created'], $payload['updated']) ? " ({$payload['created']} created, {$payload['updated']} updated)" : ''),
            'whatnot_orders' => sprintf('Recent orders completed%s', isset($payload['shows_checked']) ? " ({$payload['shows_checked']} shows checked)" : ''),
            'whatnot_shipments' => sprintf('Shipment refresh completed%s', isset($payload['shows_checked']) ? " ({$payload['shows_checked']} shows checked, " . ($payload['updated'] ?? 0) . ' updates)' : ''),
            'whatnot_ledger' => 'Rolling ledger refresh completed',
            'whatnot_nightly_reconciliation' => 'Nightly reconciliation completed',
            'whatnot_deep_backfill' => 'Deep historical backfill completed',
            'whatnot_spa_enrichment', 'whatnot_recent_refresh' => $this->enrichmentSummary($payload),
            'whatnot_show_index' => 'Show details refreshed from the index',
            default => $this->show_id ? 'Show imported' : 'Import ran',
        };
    }

    private function enrichmentSummary(array $payload): string
    {
        $parts = [];
        $analytics = $payload['analytics'] ?? null;
        if (is_array($analytics)) {
            foreach (['orders' => 'order', 'units_sold' => 'unit'] as $key => $noun) {
                $n = $analytics[$key] ?? null;
                if (is_numeric($n) && (int) $n > 0) {
                    $parts[] = number_format((int) $n) . ' ' . str($noun)->plural((int) $n);
                }
            }
            if ($parts === [] && $analytics !== []) $parts[] = 'analytics';
        }
        $shipments = (int) ($payload['shipment_count'] ?? 0);
        if ($shipments > 0) $parts[] = number_format($shipments) . ' ' . str('shipment')->plural($shipments);
        return $parts === [] ? 'Analytics pulled, nothing new to record' : 'Pulled ' . implode(', ', $parts);
    }
}
