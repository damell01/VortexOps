<?php

namespace App\AI\Services;

use App\AI\Contracts\Tool;
use App\Models\User;

/**
 * The catalog of tools the AI can call. Tools register here at boot; the intent
 * router asks for the subset a given user is authorized to run, then selects
 * one. Nothing else in the app needs to know which tools exist.
 */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return array<string, Tool> */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * The tools this user may run.
     *
     * @return array<string, Tool>
     */
    public function authorizedFor(?User $user): array
    {
        return array_filter($this->tools, fn (Tool $t) => $t->authorize($user));
    }

    /**
     * A compact, model-facing description of the authorized tools — name,
     * purpose, and argument schema — for the router's selection prompt.
     *
     * @return array<int, array{name:string, description:string, parameters:array}>
     */
    public function catalogFor(?User $user): array
    {
        return array_values(array_map(fn (Tool $t) => [
            'name'        => $t->name(),
            'description' => $t->description(),
            'parameters'  => $t->parameters(),
        ], $this->authorizedFor($user)));
    }
}
