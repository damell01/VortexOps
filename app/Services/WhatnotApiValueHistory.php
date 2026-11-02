<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WhatnotApiValueHistory
{
    /**
     * Append only when the observed value differs from the last recorded value.
     * Does not update operational inventory, ledger, shows or payroll.
     */
    public function observe(int $channelId, string $resource, string $sourceId, string $field, mixed $value): bool
    {
        if ($channelId < 1 || $sourceId === '' || strlen($sourceId) > 191 ||
            ! preg_match('/^[a-z][a-z0-9_]{0,31}$/', $resource) ||
            ! preg_match('/^[a-z][a-z0-9_]{0,99}$/', $field)) {
            throw new RuntimeException('Invalid observation identifier.');
        }

        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $encoded);

        return DB::transaction(function () use ($channelId, $resource, $sourceId, $field, $encoded, $hash) {
            // Lock the parent channel to serialize concurrent observations for the same seller.
            DB::table('whatnot_channels')->where('id', $channelId)->lockForUpdate()->firstOrFail();
            $latest = DB::table('whatnot_api_observed_values')
                ->where('whatnot_channel_id', $channelId)
                ->where('resource', $resource)
                ->where('source_id', $sourceId)
                ->where('field', $field)
                ->orderByDesc('id')->first();

            if ($latest && hash_equals($latest->value_sha256, $hash)) {
                return false;
            }

            DB::table('whatnot_api_observed_values')->insert([
                'whatnot_channel_id' => $channelId,
                'resource' => $resource,
                'source_id' => $sourceId,
                'field' => $field,
                'value_encrypted' => Crypt::encryptString($encoded),
                'value_sha256' => $hash,
                'observed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        });
    }
}
