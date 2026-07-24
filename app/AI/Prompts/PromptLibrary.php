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

    /**
     * Turns a week-over-week operational snapshot into an instruction for a
     * 3-4 sentence plain-English business briefing. Used by OpsDigestService
     * for the scheduled Wednesday/Friday ops emails.
     *
     * @param array<string,mixed> $snapshot
     */
    public function opsDigest(array $snapshot): string
    {
        $lines = [];

        $lines[] = 'Revenue so far this week: $' . number_format($snapshot['week_revenue'], 2)
            . ($snapshot['week_pacing_pct'] !== null
                ? ' (' . ($snapshot['week_pacing_pct'] >= 0 ? '+' : '') . $snapshot['week_pacing_pct'] . '% vs. the trailing 4-week average for this point in the week)'
                : ' (no trailing average yet to compare)');

        if ($snapshot['top_streamer']) {
            $lines[] = "Top streamer this week: {$snapshot['top_streamer']}.";
        }

        if ($snapshot['month_projected'] !== null) {
            $lines[] = 'Projected month total: $' . number_format($snapshot['month_projected'], 2)
                . ($snapshot['month_pacing_pct'] !== null
                    ? ' (' . ($snapshot['month_pacing_pct'] >= 0 ? '+' : '') . $snapshot['month_pacing_pct'] . '% vs. the trailing 3-month average)'
                    : '');
        }

        if (! empty($snapshot['trending_down'])) {
            $lines[] = 'Streamers trending down vs. their own recent average: ' . implode(', ', $snapshot['trending_down']) . '.';
        }

        if (! empty($snapshot['outlier_shows'])) {
            $lines[] = 'Shows with unusual revenue this week (verify the numbers): ' . implode(', ', $snapshot['outlier_shows']) . '.';
        }

        if ($snapshot['reorder_count'] > 0) {
            $lines[] = "{$snapshot['reorder_count']} product(s) are pacing toward running out before they can be restocked.";
        }

        if ($snapshot['pending_review'] > 0) {
            $lines[] = "{$snapshot['pending_review']} show(s) are still waiting on review/approval.";
        }

        $data = implode("\n", $lines);

        return <<<PROMPT
        You are writing a short business briefing for the owner of a sports-card livestream sales operation. Below is this week's operational data. Write a 3-4 sentence plain-English summary highlighting what matters most — lead with the most important thing, mention specific names/numbers from the data, and end with anything that needs action. Do not invent numbers or reasons not present in the data. Do not use bullet points or headers — write flowing prose. Do not add a greeting or sign-off.

        Data:
        {$data}

        Summary:
        PROMPT;
    }

    /**
     * Same briefing, but for an arbitrary date range (e.g. the Reports page's
     * period selector) rather than the fixed "this week" window.
     *
     * @param array<string,mixed> $snapshot
     */
    public function opsDigestForRange(array $snapshot): string
    {
        $lines = [];

        $lines[] = "Period: {$snapshot['label']} ({$snapshot['shows']} show(s)).";
        $lines[] = 'Gross revenue: $' . number_format($snapshot['gross'], 2)
            . ($snapshot['trend_pct'] !== null
                ? ' (' . ($snapshot['trend_pct'] >= 0 ? '+' : '') . $snapshot['trend_pct'] . '% vs. the prior equal-length period)'
                : ' (no prior period to compare)');

        if ($snapshot['top_streamer']) {
            $lines[] = "Top streamer this period: {$snapshot['top_streamer']}.";
        }

        if (! empty($snapshot['trending_down'])) {
            $lines[] = 'Streamers currently trending down vs. their own recent average: ' . implode(', ', $snapshot['trending_down']) . '.';
        }

        if (! empty($snapshot['outlier_shows'])) {
            $lines[] = 'Shows in this period with unusual revenue (verify the numbers): ' . implode(', ', $snapshot['outlier_shows']) . '.';
        }

        if ($snapshot['reorder_count'] > 0) {
            $lines[] = "{$snapshot['reorder_count']} product(s) are currently pacing toward running out before they can be restocked.";
        }

        if ($snapshot['pending_review'] > 0) {
            $lines[] = "{$snapshot['pending_review']} show(s) are currently waiting on review/approval.";
        }

        $data = implode("\n", $lines);

        return <<<PROMPT
        You are writing a short business briefing for the owner of a sports-card livestream sales operation, summarizing a report for a specific date range they selected. Below is the data for that period. Write a 3-4 sentence plain-English summary highlighting what matters most — lead with the most important thing, mention specific names/numbers from the data, and end with anything that needs action. Do not invent numbers or reasons not present in the data. Do not use bullet points or headers — write flowing prose. Do not add a greeting or sign-off.

        Data:
        {$data}

        Summary:
        PROMPT;
    }
}
