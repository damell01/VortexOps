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

    /**
     * Every source string the importers actually write.
     *
     * The filter used to offer "Whatnot" and "Manual". Three of the four real
     * sources were missing from it and "manual" was never written by anything,
     * so filtering by source could only ever narrow to one of four kinds of
     * row or to none at all.
     */
    public static function sourceLabels(): array
    {
        return [
            'whatnot'                => 'Manual import',
            'whatnot_show_index'     => 'Scheduled show index',
            'whatnot_spa_enrichment' => 'Analytics enrichment',
            'whatnot_recent_refresh' => 'Recent-show refresh',
        ];
    }

    public function sourceLabel(): string
    {
        return static::sourceLabels()[$this->source] ?? $this->source;
    }

    /**
     * What this row means, in a sentence someone can act on.
     *
     * A log line reading "whatnot_spa_enrichment / success" says nothing about
     * what changed. The payload knows — how many orders came back, whether
     * shipments were attached — so say that instead.
     */
    public function summary(): string
    {
        if ($this->status === 'failed') {
            return $this->show_id
                ? 'Import failed for this show'
                : 'Import failed before a show could be identified';
        }

        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];

        return match ($this->source) {
            'whatnot_spa_enrichment',
            'whatnot_recent_refresh' => $this->enrichmentSummary($payload),
            'whatnot_show_index'     => 'Show details refreshed from the index',
            default                  => $this->show_id ? 'Show imported' : 'Import ran',
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

            if ($parts === [] && $analytics !== []) {
                $parts[] = 'analytics';
            }
        }

        $shipments = (int) ($payload['shipment_count'] ?? 0);

        if ($shipments > 0) {
            $parts[] = number_format($shipments) . ' ' . str('shipment')->plural($shipments);
        }

        return $parts === []
            ? 'Analytics pulled, nothing new to record'
            : 'Pulled ' . implode(', ', $parts);
    }
}
