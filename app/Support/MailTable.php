<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A small, email-safe table for notification emails, so lists of figures
 * read as columns instead of long lines of text. Inline styles only (mail
 * clients ignore stylesheets) and on one line, so the Markdown mail layout
 * passes it through untouched.
 *
 *   MailTable::make(['Streamer', 'Shows', 'Sales', 'Profit'], $rows, numeric: [1, 2, 3])
 *
 * A cell may be a string, or ['text' => …, 'sub' => …] for a second, smaller
 * line (a show's date and streamer under its title), or ['text' => …, 'tone' =>
 * 'bad'|'good'|'muted'] to colour it. Text is escaped.
 */
class MailTable
{
    private const BORDER = '#ece9f5';

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|array{text:string,sub?:string,tone?:string}>>  $rows
     * @param  list<int>  $numeric  column indexes to right-align
     * @param  list<string|array>|null  $footer  a totals row, styled bold
     */
    public static function make(array $headers, array $rows, array $numeric = [], ?array $footer = null): HtmlString
    {
        $align = fn (int $i) => in_array($i, $numeric, true) ? 'right' : 'left';
        $head = '';
        foreach ($headers as $i => $h) {
            $head .= '<th style="padding:7px 8px;text-align:'.$align($i).';font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;border-bottom:2px solid '.self::BORDER.';white-space:nowrap">'.e($h).'</th>';
        }
        $body = '';
        foreach ($rows as $r => $row) {
            $body .= '<tr style="background:'.($r % 2 ? '#faf9fe' : '#ffffff').'">'.self::cells($row, $align, false).'</tr>';
        }
        if ($footer) {
            $body .= '<tr style="background:#f5f3ff">'.self::cells($footer, $align, true).'</tr>';
        }

        return new HtmlString('<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:4px 0 18px;font-size:13px;line-height:1.35;color:#1f2937;border:1px solid '.self::BORDER.';border-radius:8px"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>');
    }

    /**
     * A row of headline figures (Earnings $X · COGS $Y …) as tiles.
     *
     * @param  array<string, string|array{text:string,tone?:string}>  $stats  label => value
     */
    public static function stats(array $stats): HtmlString
    {
        $cells = '';
        foreach ($stats as $label => $value) {
            [$text, $tone] = is_array($value) ? [$value['text'], $value['tone'] ?? null] : [$value, null];
            $cells .= '<td style="padding:8px 10px;background:#f5f3ff;border:4px solid #ffffff;border-radius:10px;vertical-align:top"><div style="font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#7c3aed">'.e($label).'</div><div style="margin-top:2px;font-size:16px;font-weight:800;color:'.self::tone($tone, '#111827').'">'.e($text).'</div></td>';
        }

        return new HtmlString('<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:separate;margin:2px 0 14px"><tr>'.$cells.'</tr></table>');
    }

    private static function cells(array $row, callable $align, bool $bold): string
    {
        $out = '';
        foreach (array_values($row) as $i => $cell) {
            [$text, $sub, $tone] = is_array($cell) ? [$cell['text'] ?? '', $cell['sub'] ?? null, $cell['tone'] ?? null] : [(string) $cell, null, null];
            $out .= '<td style="padding:7px 8px;text-align:'.$align($i).';border-bottom:1px solid '.self::BORDER.';vertical-align:top;'.($bold ? 'font-weight:800;' : '').($align($i) === 'right' ? 'white-space:nowrap;' : '').'color:'.self::tone($tone, '#1f2937').'">'
                .e($text).($sub !== null && $sub !== '' ? '<div style="font-size:11px;color:#6b7280;font-weight:400">'.e($sub).'</div>' : '').'</td>';
        }

        return $out;
    }

    private static function tone(?string $tone, string $default): string
    {
        return match ($tone) {
            'bad' => '#dc2626',
            'good' => '#047857',
            'muted' => '#9ca3af',
            default => $default,
        };
    }
}
