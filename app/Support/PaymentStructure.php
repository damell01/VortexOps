<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Streamer;

/**
 * Resolves and applies default compensation for Streamer and Fulfillment team
 * members while preserving field-level individual overrides.
 *
 * Legacy rows keep compensation_override_fields=null and remain untouched until
 * an admin explicitly opts them into a team structure. Once adopted, [] means
 * inherit everything and named fields are the only individual overrides.
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
        return is_array($decoded)
            ? array_intersect_key($decoded, array_flip(self::FIELDS))
            : [];
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
            ->causedBy(auth()->user())
            ->withProperties(['member_type' => $memberType, 'values' => $payload])
            ->log(ucfirst($memberType) . ' payment structure updated');
    }

    public static function adoptDefaults(Streamer $member): void
    {
        $member->update(['compensation_override_fields' => []]);

        activity('payment_structure')
            ->causedBy(auth()->user())
            ->performedOn($member)
            ->withProperties(['member_type' => $member->isFulfillment() ? 'fulfillment' : 'streamer'])
            ->log('Team member adopted payment structure defaults');
    }

    /** @param array<string,mixed> $overrides */
    public static function saveOverrides(Streamer $member, array $overrides): void
    {
        $allowed = array_intersect_key($overrides, array_flip(self::FIELDS));
        $member->forceFill([
            'compensation_override_fields' => array_values(array_keys($allowed)),
        ] + $allowed)->save();

        activity('payment_structure')
            ->causedBy(auth()->user())
            ->performedOn($member)
            ->withProperties(['overrides' => $allowed])
            ->log('Individual compensation overrides updated');
    }

    public static function resetOverrides(Streamer $member): void
    {
        self::adoptDefaults($member);
    }

    /** @return array{member_type:string,structure:string,defaults:array<string,mixed>,overrides:array<string,mixed>,effective:array<string,mixed>,legacy:bool,version:string} */
    public static function resolve(Streamer $member): array
    {
        $memberType = $member->isFulfillment() ? 'fulfillment' : 'streamer';
        $defaults = self::defaults($memberType);
        $overrideFields = $member->compensation_override_fields;
        $legacy = $overrideFields === null;

        $row = [];
        foreach (self::FIELDS as $field) {
            // Read the stored per-person value without invoking the effective
            // compensation accessors. Those accessors call back into this
            // resolver, so getRawOriginal() is the deliberate recursion break.
            $row[$field] = self::normalize($field, $member->getRawOriginal($field));
        }

        if ($legacy || $defaults === []) {
            return [
                'member_type' => $memberType,
                'structure' => ucfirst($memberType) . ' Payment Structure',
                'defaults' => $defaults,
                'overrides' => $row,
                'effective' => $row,
                'legacy' => $legacy,
                'version' => self::VERSION,
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
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $effective)) {
                $effective[$field] = $row[$field];
            }
        }

        return [
            'member_type' => $memberType,
            'structure' => ucfirst($memberType) . ' Payment Structure',
            'defaults' => $defaults,
            'overrides' => $overrides,
            'effective' => $effective,
            'legacy' => false,
            'version' => self::VERSION,
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
