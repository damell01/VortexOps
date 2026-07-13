<?php

namespace App\AI\Services;

use App\AI\Enums\AiTask;
use App\Models\Setting;

/**
 * The single place that decides *which model* and *what generation settings* an
 * AI task runs with. Resolution order for every value: admin Settings first,
 * then the config/ai.php default. No model name is ever hardcoded at a call
 * site — callers name a task, this class names the model.
 */
final class ModelRouter
{
    /**
     * The concrete model to use for a task.
     */
    public function modelFor(AiTask $task): string
    {
        $cfg = config("ai.tasks.{$task->value}", []);

        $fromSettings = isset($cfg['setting']) ? Setting::get($cfg['setting']) : null;

        return $fromSettings ?: ($cfg['default'] ?? config('ai.tasks.chat.default'));
    }

    /**
     * The normalised generation options for a task, with per-call overrides
     * taking precedence over the resolved defaults. Vision/embedding tasks
     * ignore the text-generation knobs that don't apply to them.
     *
     * @param  array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    public function generationOptions(AiTask $task, array $overrides = []): array
    {
        $base = [
            'temperature'    => $this->setting('ai.generation.temperature'),
            'top_p'          => $this->setting('ai.generation.top_p'),
            'max_tokens'     => $this->setting('ai.generation.max_tokens'),
            'context_length' => $this->setting('ai.generation.context_length'),
        ];

        if ($task === AiTask::Json) {
            $base['format'] = 'json';
        }

        // Overrides win; nulls in overrides are ignored so a caller can pass a
        // sparse array without wiping the resolved defaults.
        foreach ($overrides as $key => $value) {
            if ($value !== null) {
                $base[$key] = $value;
            }
        }

        return $this->coerceTypes(array_filter($base, fn ($v) => $v !== null));
    }

    /**
     * Settings are stored as strings, but Ollama rejects a string where it wants
     * a number ("option \"temperature\" must be of type float32"). Cast the
     * numeric generation knobs to their real types before they leave the router.
     *
     * @param  array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function coerceTypes(array $options): array
    {
        $floats = ['temperature', 'top_p'];
        $ints    = ['max_tokens', 'context_length'];

        foreach ($floats as $key) {
            if (isset($options[$key])) {
                $options[$key] = (float) $options[$key];
            }
        }
        foreach ($ints as $key) {
            if (isset($options[$key])) {
                $options[$key] = (int) $options[$key];
            }
        }

        return $options;
    }

    public function streamingEnabled(): bool
    {
        return (bool) $this->setting('ai.streaming');
    }

    public function cacheTtl(): int
    {
        return (int) $this->setting('ai.cache_ttl');
    }

    /**
     * Read a Settings-backed config value: the config entry names a Settings key
     * and a default, and Settings wins when present.
     */
    private function setting(string $configKey): mixed
    {
        $cfg = config($configKey, []);

        if (! is_array($cfg)) {
            return $cfg;
        }

        $fromSettings = isset($cfg['setting']) ? Setting::get($cfg['setting']) : null;

        return $fromSettings !== null && $fromSettings !== ''
            ? $fromSettings
            : ($cfg['default'] ?? null);
    }
}
