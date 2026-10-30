<?php

namespace App\Services;

/**
 * Extract documented REST v2 order fields without guessing from legacy GraphQL.
 * Money is an {amount, currency} object; reject non-USD rather than silently
 * recording foreign-currency amounts as dollars.
 */
final class WhatnotV2OrderMapper
{
    public function map(array $order): array
    {
        $id = $order['id'] ?? null;
        $showId = $order['show_id'] ?? null;
        if ((! is_string($id) && ! is_int($id)) || trim((string) $id) === ''
            || ! is_string($showId)
            || ! preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i', $showId)) {
            throw new \InvalidArgumentException('Order and canonical show IDs are required.');
        }
        $amounts = $order['amounts'] ?? [];
        if (! is_array($amounts)) {
            throw new \InvalidArgumentException('Invalid order amounts.');
        }
        $money = function (string $field) use ($amounts): ?string {
            $value = $amounts[$field]['amount'] ?? null;
            if ($value === null) {
                return null;
            }
            if (! is_array($value) || ($value['currency'] ?? null) !== 'USD'
                || ! is_string($value['amount'] ?? null)
                || ! preg_match('/^-?\\d{1,12}(?:\\.\\d{1,2})?$/', $value['amount'])) {
                throw new \InvalidArgumentException('Invalid or unsupported currency for '.$field);
            }
            return $value['amount'];
        };
        return [
            'whatnot_order_id' => trim((string) $id),
            'whatnot_show_id' => strtolower($showId),
            'status' => $order['status'] ?? null,
            'created_at_source' => $order['created_at'] ?? null,
            'updated_at_source' => $order['updated_at'] ?? null,
            'subtotal_usd' => $money('subtotal'),
            'shipping_usd' => $money('shipping'),
            'tax_usd' => $money('tax'),
            'total_usd' => $money('total'),
            'transaction_type' => $order['transaction_type'] ?? null,
        ];
    }
}
