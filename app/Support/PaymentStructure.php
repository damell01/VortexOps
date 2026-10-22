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
        'house_cut_percentage',
        'sales_split_basis',
        'package_rate',
        'hourly_rate',
        'pwe_rate',
        'label_rate',
        'include_tips',
        'custom_payout_formula',
        'burden_rate_type',
        'burden_rate_value',
        'monthly_burden_addback',
        'monthly_burden_payments',
    ];

    /**
     * Rates for anyone paid hourly + shipments + profit share. They sit apart
     * from a team's main rates so a business can default to a sales split
     * (Estill's independents) and still have rates ready for its hourly
     * streamers, or make this pay type its team default.
     */
    public const SHIFT_FIELDS = ['hourly_rate', 'pwe_rate', 'label_rate', 'payout_percentage', 'burden_rate_type', 'burden_rate_value'];

    /**
     * The setting holding a team's defaults. Each business can have its own
     * (Estill streamers are paid differently from Vortex Collects'); a
     * business that has not saved any uses the shared ones.
     */
    public static function settingKey(string $memberType, ?int $businessId = null): string
    {
        return 'payment_structure_' . ($memberType === 'fulfillment' ? 'fulfillment' : 'streamer')
            . ($businessId ? '_business_' . $businessId : '');
    }

    /** @return array<string,mixed> A business's hourly + shipments + profit share rates, else the shared ones. */
    public static function shiftDefaults(?int $businessId = null): array
    {
        $own = $businessId ? Setting::get(self::settingKey('streamer', $businessId) . '_shift') : null;
        $raw = $own !== null && trim((string) $own) !== '' ? $own : Setting::get(self::settingKey('streamer') . '_shift');
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? array_intersect_key($decoded, array_flip(self::SHIFT_FIELDS)) : [];
    }

    public static function saveShiftDefaults(array $values, ?int $businessId = null): void
    {
        $payload = [];
        foreach (self::SHIFT_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $payload[$field] = self::normalize($field, $values[$field]);
            }
        }
        Setting::set(self::settingKey('streamer', $businessId) . '_shift', json_encode($payload));
    }

    /** @return array<string,mixed> */
    public static function defaults(string $memberType, ?int $businessId = null): array
    {
        if ($businessId && self::hasOwnDefaults($memberType, $businessId)) {
            return self::decode(Setting::get(self::settingKey($memberType, $businessId)));
        }

        return self::decode(Setting::get(self::settingKey($memberType)));
    }

    /** Whether a business has saved its own defaults rather than using the shared ones. */
    public static function hasOwnDefaults(string $memberType, int $businessId): bool
    {
        $raw = Setting::get(self::settingKey($memberType, $businessId));

        return $raw !== null && trim((string) $raw) !== '';
    }

    /** Go back to the shared defaults for this business. */
    public static function clearBusinessDefaults(string $memberType, int $businessId): void
    {
        Setting::set(self::settingKey($memberType, $businessId), '');
        if ($memberType === 'streamer') {
            Setting::set(self::settingKey('streamer', $businessId) . '_shift', '');
        }
    }

    /** @return array<string,mixed> */
    private static function decode(mixed $raw): array
    {
        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded)
            ? array_intersect_key($decoded, array_flip(self::FIELDS))
            : [];
    }

    public static function saveDefaults(string $memberType, array $values, ?int $businessId = null): void
    {
        $payload = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $payload[$field] = self::normalize($field, $values[$field]);
            }
        }

        Setting::set(self::settingKey($memberType, $businessId), json_encode($payload));

        $activity = activity('payment_structure');
        if ($user = auth()->user()) {
            $activity->causedBy($user);
        }
        $activity
            ->withProperties(['member_type' => $memberType, 'business_id' => $businessId, 'values' => $payload])
            ->log(ucfirst($memberType) . ' payment structure updated'
                . ($businessId ? ' for ' . (\App\Models\Business::whereKey($businessId)->value('name') ?? "business #{$businessId}") : ''));
    }

    public static function adoptDefaults(Streamer $member): void
    {
        $member->update(['compensation_override_fields' => []]);

        $activity = activity('payment_structure')->performedOn($member);
        if ($user = auth()->user()) {
            $activity->causedBy($user);
        }
        $activity
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

        $activity = activity('payment_structure')->performedOn($member);
        if ($user = auth()->user()) {
            $activity->causedBy($user);
        }
        $activity
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
        $resolved = self::resolveStored($member);

        // The Payroll Simulator's what-if rates, held only on an in-memory
        // copy of the person (Streamer::$whatIfCompensation), win over
        // whatever is stored. Real people never carry any.
        if ($member->whatIfCompensation) {
            foreach (array_intersect_key($member->whatIfCompensation, array_flip(self::FIELDS)) as $field => $value) {
                $resolved['effective'][$field] = self::normalize($field, $value);
            }
        }

        return $resolved;
    }

    /** @return array{member_type:string,structure:string,defaults:array<string,mixed>,overrides:array<string,mixed>,effective:array<string,mixed>,legacy:bool,version:string} */
    private static function resolveStored(Streamer $member): array
    {
        $memberType = $member->isFulfillment() ? 'fulfillment' : 'streamer';
        $defaults = self::defaults($memberType, $member->getRawOriginal('business_id') ?? $member->business_id);
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

        // Paid hourly + shipments + profit share: the team's rates for that
        // pay type fill whatever this person hasn't set themselves.
        if (($effective['payout_type'] ?? null) === 'shift_share' && $memberType === 'streamer') {
            foreach (self::shiftDefaults($member->getRawOriginal('business_id') ?? $member->business_id) as $field => $value) {
                if ($value !== null && ! array_key_exists($field, $overrides)) {
                    $effective[$field] = $value;
                }
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
        if (in_array($field, ['payout_percentage', 'house_cut_percentage', 'package_rate', 'hourly_rate', 'pwe_rate', 'label_rate', 'burden_rate_value', 'monthly_burden_addback'], true)) {
            return $value === null || $value === '' ? null : (float) $value;
        }
        if ($field === 'monthly_burden_payments') {
            return $value === null || $value === '' ? null : (int) $value;
        }
        if ($field === 'include_tips') {
            return (bool) $value;
        }
        return $value;
    }
}
