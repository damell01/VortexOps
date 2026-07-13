<?php

namespace App\AI\Prompts;

/**
 * The one place prompt text lives. Services ask for a named, rendered prompt
 * rather than embedding strings at the call site — so prompts can be reviewed,
 * tuned, and reused without touching business logic.
 */
final class PromptLibrary
{
    /**
     * Instructs a small model to pick the right tool (or none) for a user
     * message and return a strict JSON decision. The catalog is injected so the
     * model only ever sees tools the user is authorized to run.
     *
     * @param array<int, array{name:string, description:string, parameters:array}> $catalog
     */
    public function intentClassifier(string $message, array $catalog): string
    {
        $tools = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
        You are the intent router for an operations platform. Decide whether the
        user's message should call one of the available tools, or is just
        conversation.

        Available tools (JSON):
        {$tools}

        User message:
        "{$message}"

        Respond with ONLY a JSON object, no prose, in this exact shape:
        {"tool": "<tool name or null>", "arguments": { ... }, "confidence": <0.0-1.0>}

        Rules:
        - Use a tool only when the message clearly maps to it and you can fill its
          required arguments from the message.
        - Put every argument value the tool needs in "arguments", matching the
          parameter names exactly.
        - If no tool fits, set "tool" to null and "arguments" to {}.
        - confidence reflects how sure you are of the tool choice.
        PROMPT;
    }

    /**
     * Instructs a vision model to read a packing slip / invoice image and return
     * its line items as strict JSON.
     */
    public function slipExtraction(): string
    {
        return <<<'PROMPT'
        Look at this packing slip or invoice image. Extract every product line item you can read.
        Return ONLY a valid JSON object — no explanation, no markdown, no extra text — in exactly this format:
        {"lines":[{"description":"full product name","case_count":1,"unit_cost":89.99,"sku":"ABC123","barcode":"012345678901"}]}
        Rules:
        - description: full product name or description (required)
        - case_count: number of cases, units, or quantity as an integer (use 1 if not shown)
        - unit_cost: price per unit as a plain number, no $ sign (use null if not shown)
        - sku: item number, SKU, or part number (use null if not shown)
        - barcode: UPC, EAN, or barcode number (use null if not shown)
        Include every line item. If multiple pages, include all.
        PROMPT;
    }

    /**
     * Frames a tool's raw result so the chat model answers the user in natural
     * language grounded strictly in that data.
     */
    public function answerFromToolResult(string $userMessage, string $toolName, string $toolSummary): string
    {
        return <<<PROMPT
        The user asked: "{$userMessage}"

        You ran the "{$toolName}" tool and it returned:
        {$toolSummary}

        Answer the user's question directly and concisely using ONLY the data
        above. Do not invent numbers. If the data doesn't fully answer the
        question, say what's missing.
        PROMPT;
    }
}
