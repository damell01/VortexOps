<?php

namespace App\AI\Listeners;

use App\AI\Events\AiCallCompleted;
use App\Models\AiInteraction;
use Illuminate\Support\Facades\Schema;

/**
 * Persists each completed AI call as telemetry. Deliberately best-effort:
 * monitoring must never break an AI request, so every failure here is swallowed.
 */
final class RecordAiInteraction
{
    public function handle(AiCallCompleted $event): void
    {
        if (! config('ai.monitoring.enabled', true)) {
            return;
        }

        try {
            if (! Schema::hasTable('ai_interactions')) {
                return;
            }

            $r = $event->response;

            AiInteraction::create([
                'provider'   => $r->provider,
                'task'       => $r->task->value,
                'model'      => $r->model,
                'latency_ms' => $r->latencyMs,
                'success'    => $r->success,
                'error'      => $r->error ? mb_substr($r->error, 0, 500) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Telemetry is never allowed to interfere with the actual call.
        }
    }
}
