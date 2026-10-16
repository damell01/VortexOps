<?php

namespace App\Support;

/** Arithmetic only. The editor, validation and real payouts use this same parser. */
class PayoutFormula
{
    public const FIELDS = [
        'gross_revenue' => ['Gross revenue', 'Full show gross, including on collaboration shows.', 1000],
        'whatnot_net' => ['Whatnot net', 'Full show estimated net earnings.', 850],
        'streamer_share_net' => ['Primary streamer net', 'Whatnot net for the primary streamer; zero for collaborators.', 850],
        'product_cost' => ['Product cost', 'Product cost recorded on the show report.', 300],
        'units_sold' => ['Units sold', 'Units sold reported by Whatnot.', 50],
        'shipments_count' => ['Shipments', 'Show report shipment count, falling back to imported shipments.', 40],
        'pwe_count' => ['Envelopes (PWE)', 'Envelope count entered on the show report; zero when missing.', 10],
        'label_count' => ['Packages / labels', 'Package count entered on the show report; zero when missing.', 30],
        'hours_worked' => ['Hours worked', 'Report hours, falling back to this person’s clocked show time. Missing hours are zero.', 4],
        'tips' => ['Show tips', 'Full tips for the show, before splitting between streamers.', 20],
        'tip_share' => ['Person’s tip share', 'This person’s allocated tips, with collaboration rounding.', 20],
        'payout_percentage' => ['Pay percentage', 'This person’s rate as a number, e.g. 8 means 8%. Divide by 100.', 8],
        'hourly_rate' => ['Hourly rate', 'This person’s configured hourly rate.', 15],
        'package_rate' => ['Flat / package rate', 'This person’s flat package rate.', 25],
        'pwe_rate' => ['Envelope rate', 'Rate per envelope.', 0.10],
        'label_rate' => ['Package / label rate', 'Rate per package.', 0.25],
        'show_duration_hours' => ['Show length (hours)', 'Whatnot broadcast length; use Hours worked for wages.', 3],
        'show_duration_minutes' => ['Show length (minutes)', 'Whatnot broadcast length in minutes.', 180],
    ];

    public static function samples(): array
    {
        return array_map(fn ($field) => $field[2], self::FIELDS);
    }

    /** Validate syntax without treating sample values as real inputs (e.g. zero denominators). */
    public static function validate(string $formula): void
    {
        self::parse($formula);
    }

    public static function evaluate(string $formula, array $variables): float
    {
        $stack = [];
        foreach (self::parse($formula) as $token) {
            if (is_float($token)) {
                $stack[] = $token;
            } elseif (array_key_exists($token, self::FIELDS)) {
                if (! array_key_exists($token, $variables) || ! is_numeric($variables[$token])) {
                    throw new \RuntimeException("Missing formula value: {$token}");
                }
                $stack[] = (float) $variables[$token];
            } elseif ($token === 'negate') {
                $stack[] = -array_pop($stack);
            } else {
                $right = array_pop($stack);
                $left = array_pop($stack);
                $stack[] = match ($token) {
                    '+' => $left + $right,
                    '-' => $left - $right,
                    '*' => $left * $right,
                    '/' => $right == 0.0 ? throw new \RuntimeException('Custom payout formula cannot divide by zero.') : $left / $right,
                };
            }
            if (! is_finite(end($stack))) {
                throw new \RuntimeException('Custom payout formula must produce a finite number.');
            }
        }
        return $stack[0];
    }

    /** Strict recursive descent: no skipped characters, implicit multiplication or executable code. */
    private static function parse(string $formula): array
    {
        if (strlen($formula) > 1000 || trim($formula) === '') {
            throw new \RuntimeException('Enter a formula of up to 1,000 characters.');
        }
        preg_match_all('/\s+|[A-Za-z_]\w*|(?:\d+(?:\.\d*)?|\.\d+)|[()+*\/\-]|./s', $formula, $matches);
        $tokens = array_values(array_filter($matches[0], fn ($token) => trim($token) !== ''));
        $position = 0;
        $output = [];
        $expression = $term = $factor = null;
        $factor = function () use (&$factor, &$expression, &$tokens, &$position, &$output): void {
            $token = $tokens[$position++] ?? null;
            if ($token === '+' || $token === '-') {
                $factor();
                if ($token === '-') $output[] = 'negate';
            } elseif ($token === '(') {
                $expression();
                if (($tokens[$position++] ?? null) !== ')') throw new \RuntimeException('Custom payout formula has mismatched parentheses.');
            } elseif ($token !== null && is_numeric($token)) {
                $output[] = (float) $token;
            } elseif ($token !== null && array_key_exists($token, self::FIELDS)) {
                $output[] = $token;
            } else {
                throw new \RuntimeException('Expected a number, available field or opening parenthesis' . ($token !== null ? "; found “{$token}”." : '.'));
            }
        };
        $term = function () use (&$factor, &$tokens, &$position, &$output): void {
            $factor();
            while (in_array($tokens[$position] ?? null, ['*', '/'], true)) {
                $operator = $tokens[$position++];
                $factor();
                $output[] = $operator;
            }
        };
        $expression = function () use (&$term, &$tokens, &$position, &$output): void {
            $term();
            while (in_array($tokens[$position] ?? null, ['+', '-'], true)) {
                $operator = $tokens[$position++];
                $term();
                $output[] = $operator;
            }
        };
        $expression();
        if ($position !== count($tokens)) throw new \RuntimeException('Unexpected formula text. Use an operator between each field or number.');
        return $output;
    }
}
