<?php

namespace App\AI\Contracts;

use App\AI\DTOs\ToolResult;
use App\Models\User;

/**
 * A capability the AI can invoke to answer a question or take an action against
 * real app data. Tools are how the assistant reaches beyond text — the intent
 * router picks one from the authorized catalog and hands it structured
 * arguments. Every tool owns its own authorization so a client can never reach
 * data their role can't see.
 */
interface Tool
{
    /** Stable slug the router selects by, e.g. "inventory.lookup". */
    public function name(): string;

    /** One line: what it does and when to use it (shown to the model). */
    public function description(): string;

    /**
     * Argument schema the model fills in — name => ['type','description','required'].
     *
     * @return array<string, array{type:string, description:string, required?:bool}>
     */
    public function parameters(): array;

    /**
     * Whether this user may run the tool. Null = unauthenticated.
     */
    public function authorize(?User $user): bool;

    /**
     * Execute against the given arguments.
     *
     * @param array<string,mixed> $arguments
     */
    public function run(array $arguments): ToolResult;
}
