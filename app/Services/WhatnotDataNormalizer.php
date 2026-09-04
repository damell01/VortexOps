<?php

namespace App\Services;

/**
 * Normalizes raw output from the Whatnot Playwright scraper before it reaches
 * Laravel import logic. Laravel only ever sees clean, typed, deduplicated data.
 */
class WhatnotDataNormalizer
{
    private const CARD_BRANDS = [
        'National Treasures', 'Immaculate Collection', 'Stadium Club',
        'Upper Deck', 'Topps Chrome', 'Bowman Chrome',
        'Topps', 'Bowman', 'Panini', 'Donruss', 'Fleer', 'Score',
        'Select', 'Prizm', 'Optic', 'Mosaic', 'Chronicles',
        'Contenders', 'Absolute', 'Limited', 'Leaf', 'Pacific', 'SP',
    ];

    private const GRADE_COMPANIES = ['PSA', 'BGS', 'CGC', 'SGC', 'HGA', 'CSG', 'KSA'];

    private const SPORTS = [
        'Baseball'   => ['MLB', 'baseball'],
        'Basketball' => ['NBA', 'basketball'],
        'Football'   => ['NFL', 'football'],
        'Hockey'     => ['NHL', 'hockey'],
        'Soccer'     => ['MLS', 'soccer'],
        'Golf'       => ['PGA', 'golf'],
        'Pokemon'    => ['pokemon', 'poke'],
    ];

    public function normalizeShows(array $rawShows): array
    {
        $seen = [];
        $results = [];
        foreach ($rawShows as $show) {
            $normalized = $this->normalizeShow($show);
            $hash = $normalized['_hash'];
            if (isset($seen[$hash])) continue;
            $seen[$hash] = true;
            $results[] = $normalized;
        }
        return $results;
    }

    public function normalizeShow(array $raw): array
    {
        $title = $this->normalizeTitle($raw['title'] ?? '');
        $showDate = $this->normalizeDate($raw['show_date'] ?? null);
        $avgOrderRating = $raw['avg_order_rating'] ?? null;

        $show = [
            'title' => $title,
            'show_date' => $showDate,
            'show_date_raw' => $raw['show_date_raw'] ?? null,
            'detail_url' => $this->normalizeUrl($raw['detail_url'] ?? null),
            'gross_revenue' => $this->normalizeMoney($raw['gross_revenue'] ?? null),
            'whatnot_net' => $this->normalizeMoney($raw['whatnot_net'] ?? null),
            'completed_earnings' => $this->normalizeMoney($raw['completed_earnings'] ?? null),
            'avg_order_value' => $this->normalizeMoney($raw['avg_order_value'] ?? null),
            'giveaway_spend' => $this->normalizeMoney($raw['giveaway_spend'] ?? null),
            'avg_order_rating' => $avgOrderRating !== null ? round((float) $avgOrderRating, 2) : null,
            'units_sold' => $this->normalizeInt($raw['units_sold'] ?? null),
            'giveaways_count' => $this->normalizeInt($raw['giveaways_count'] ?? null),
            'buyers_count' => $this->normalizeInt($raw['buyers_count'] ?? null),
            'first_time_buyers' => $this->normalizeInt($raw['first_time_buyers'] ?? null),
            'returning_buyers' => $this->normalizeInt($raw['returning_buyers'] ?? null),
            'shares_count' => $this->normalizeInt($raw['shares_count'] ?? null),
            'max_concurrent_viewers' => $this->normalizeInt($raw['max_concurrent_viewers'] ?? null),
            'total_views' => $this->normalizeInt($raw['total_views'] ?? null),
            'show_duration' => $this->normalizeInt($raw['show_duration'] ?? null),
            '_card_meta' => $this->extractCardMetadata($title),
            '_hash' => $this->hashShow($title, $showDate, $raw['gross_revenue'] ?? null),
            '_raw' => $raw,
        ];

        if ($show['buyers_count'] !== null && $show['total_views'] !== null && $show['buyers_count'] > $show['total_views']) {
            $show['buyers_count'] = $show['total_views'];
        }
        return $show;
    }

    public function normalizeOrders(array $rawOrders): array
    {
        $seen = [];
        $results = [];
        foreach ($rawOrders as $order) {
            $normalized = $this->normalizeOrder($order);
            if ($normalized === null) continue;
            $hash = $this->hashOrder($normalized);
            if (isset($seen[$hash])) continue;
            $seen[$hash] = true;
            $results[] = array_merge($normalized, ['_hash' => $hash]);
        }
        return $results;
    }

    public function extractCardMetadata(string $text): array
    {
        if (! $text) return [];
        $meta = [];
        if (preg_match('/(?<![#\/\d])(19[89]\d|20[0-3]\d)(?!\d)/', $text, $m)) $meta['year'] = (int) $m[1];
        if (preg_match('/#\s*([A-Z0-9][A-Z0-9\-\/]{0,9})/i', $text, $m)) $meta['card_number'] = strtoupper(trim($m[1]));
        if (preg_match('/\/(\d{1,5})(?!\d)(?![\s]*PSA)/', $text, $m)) {
            $n = (int) $m[1];
            if ($n < 1980 || $n > 2030) $meta['print_run'] = $n;
        }
        $gradePattern = implode('|', self::GRADE_COMPANIES);
        if (preg_match("/\\b({$gradePattern})\\s+(\\d+(?:\\.\\d+)?)\\b/i", $text, $m)) {
            $meta['grade_company'] = strtoupper($m[1]);
            $meta['grade'] = (float) $m[2];
        }
        foreach (self::CARD_BRANDS as $brand) {
            if (stripos($text, $brand) !== false) { $meta['brand'] = $brand; break; }
        }
        foreach (self::SPORTS as $sport => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($text, $kw) !== false) { $meta['sport'] = $sport; break 2; }
            }
        }
        if (preg_match('/\b(auto(?:graph)?|AU)\b/i', $text)) $meta['is_auto'] = true;
        if (preg_match('/\b(RC|Rookie|rookie\s+card)\b/i', $text)) $meta['is_rookie'] = true;
        if (preg_match('/\bRefractor\b/i', $text)) $meta['variation'] = 'Refractor';
        elseif (preg_match('/\bPrizm\b/i', $text)) $meta['variation'] = 'Prizm';
        elseif (preg_match('/\bOptic\b/i', $text)) $meta['variation'] = 'Optic';
        return $meta;
    }

    private function normalizeOrder(array $raw): ?array
    {
        $itemName = $this->normalizeTitle($raw['item_name'] ?? '');
        $buyer = $this->normalizeUsername($raw['buyer'] ?? null);
        if (! $itemName && ! $buyer && ! ($raw['lot_number'] ?? null)) return null;
        return [
            'order_id' => $raw['order_id'] ?? null,
            'buyer' => $buyer,
            'item_name' => $itemName,
            'lot_number' => isset($raw['lot_number']) ? (int) $raw['lot_number'] : null,
            'quantity' => max(1, (int) ($raw['quantity'] ?? 1)),
            'unit_price' => $this->normalizeMoney($raw['unit_price'] ?? null),
            'total_price' => $this->normalizeMoney($raw['total_price'] ?? null),
            'status' => $this->normalizeOrderStatus($raw['status'] ?? null),
            'raw_data' => isset($raw['raw_text']) ? substr((string) $raw['raw_text'], 0, 500) : null,
            '_card_meta' => $this->extractCardMetadata($itemName),
        ];
    }

    public function normalizeTitle(?string $title): string
    {
        if (! $title) return '';
        $title = html_entity_decode(trim($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/', ' ', $title) ?? $title;
    }

    public function normalizeUsername(?string $username): ?string
    {
        if (! $username) return null;
        return strtolower(ltrim(trim($username), '@')) ?: null;
    }

    private function normalizeDate(?string $date): ?string
    {
        if (! $date) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $date;
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
        return null;
    }

    private function normalizeMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);
        $float = (float) $cleaned;
        return $float === 0.0 ? null : round($float, 2);
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $int = (int) preg_replace('/[^0-9]/', '', (string) $value);
        return $int === 0 ? null : $int;
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (! $url) return null;
        $url = trim($url);
        return str_starts_with($url, 'http') ? $url : null;
    }

    private function normalizeOrderStatus(?string $status): string
    {
        return match (strtolower(trim($status ?? ''))) {
            'sold', 'completed', 'done', 'paid', 'success' => 'completed',
            'refunded', 'refund' => 'refunded',
            'cancelled', 'canceled' => 'cancelled',
            'pending' => 'pending',
            'shipped' => 'shipped',
            default => 'completed',
        };
    }

    private function hashShow(string $title, ?string $date, mixed $revenue): string
    {
        return md5($title . '|' . ($date ?? '') . '|' . ($revenue ?? ''));
    }

    private function hashOrder(array $order): string
    {
        return md5(($order['buyer'] ?? '') . '|' . ($order['item_name'] ?? '') . '|' . ($order['lot_number'] ?? ''));
    }
}
