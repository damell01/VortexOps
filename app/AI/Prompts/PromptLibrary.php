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
}
