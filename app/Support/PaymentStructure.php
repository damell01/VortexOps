<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Streamer;

/**
 * Resolves the effective pay terms for a team member.
 *
 * Team defaults live in Settings as JSON. Individual records keep their
 * existing rate columns, but only fields explicitly named in
 * compensation_override_fields replace the team default. A null override list
 * means "legacy member": use the row exactly as it existed before payment
 * structures were introduced so deploying this feature cannot silently change
 * anybody's pay.
 */
class PaymentStructure
{
    public const VERSION = 'role-defaults-v1';

    public const FIELDS = [
        'payout_type',
        'payout_cadence',
        'payout_percentage',
        'package_rate',
        'hourly_rate',
        'pwe_rate',
        'label_rate',
        'include_tips',
        'custom_payout_formula',
        'burden_rate_type',
        'burden_rate_value',
    ];

    public static function settingKey(string $memberType): string
    {
        return 'payment_structure_' . ($memberType === 'fulfillment' ? 'fulfillment' : 'streamer');
    }

    /** @return array<string,mixed> */
    public static function defaults(string $memberType): array
    {
        $raw = Setting::get(self::settingKey($memberType));
        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }

        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_intersect_key($decoded, array_flip(self::FIELDS));
    }

    public static function saveDefaults(string $memberType, array $values): void
    {
        $payload = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $payload[$field] = self::normalize($field, $values[$field]);
            }
        }

        Setting::set(self::settingKey($memberType), json_encode($payload));

        activity('payment_structure')
            ->withProperties(['member_type' => $memberType, 'values' => $payload])
            ->log(ucfirst($memberType) . ' payment structure updated');
    }

    /**
     * @return array{member_type:string,structure:string,defaults:array<string,mixed>,overrides:array<string,mixed>,effective:array<string,mixed>,legacy:bool,version:string}
     */
    public static function resolve(Streamer $member): array
    {
        $memberType = $member->isFulfillment() ? 'fulfillment' : 'streamer';
        $defaults = self::defaults($memberType);
        $overrideFields = $member->compensation_override_fields;
        $legacy = $overrideFields === null;

        $row = [];
        foreach (self::FIELDS as $field) {
            $row[$field] = self::normalize($field, $member->getAttribute($field));
        }

        // Until an admin deliberately opts a legacy record into inheritance,
        // the row remains authoritative. This is the safest migration behavior.
        if ($legacy || $defaults === []) {
            return [
                'member_type' => $memberType,
                'structure'   => ucfirst($memberType) . ' Payment Structure',
                'defaults'    => $defaults,
                'overrides'   => $row,
                'effective'   => $row,
                'legacy'      => $legacy,
                'version'     => self::VERSION,
            ];
        }

        $overrideFields = array_values(array_intersect(self::FIELDS, is_array($overrideFields) ? $overrideFields : []));
        $overrides = [];
        foreach ($overrideFields as $field) {
            $overrides[$field] = $row[$field];
        }

        $effective = $defaults;
        foreach ($overrides as $field => $value) {
            $effective[$field] = $value;
        }

        // A structure may intentionally omit optional fields. Preserve the
        // model/database fallback rather than turning an omitted value into an
        // accidental null calculation input.
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $effective)) {
                $effective[$field] = $row[$field];
            }
        }

        return [
            'member_type' => $memberType,
            'structure'   => ucfirst($memberType) . ' Payment Structure',
            'defaults'    => $defaults,
            'overrides'   => $overrides,
            'effective'   => $effective,
            'legacy'      => false,
            'version'     => self::VERSION,
        ];
    }

    public static function effective(Streamer $member, string $field, mixed $fallback = null): mixed
    {
        return self::resolve($member)['effective'][$field] ?? $fallback;
    }

    private static function normalize(string $field, mixed $value): mixed
    {
        if (in_array($field, ['payout_percentage', 'package_rate', 'hourly_rate', 'pwe_rate', 'label_rate', 'burden_rate_value'], true)) {
            return $value === null || $value === '' ? null : (float) $value;
        }

        if ($field === 'include_tips') {
            return (bool) $value;
        }

        return $value;
    }
}
