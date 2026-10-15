<?php

namespace App\Support;

use App\Models\Streamer;

/**
 * The Pay section on a user's profile: the same individual overrides as
 * Rates & Adjustments, so pay is set where the person is set up instead of in
 * a second place.
 *
 * A blank field means "use the team default"; a filled one is that person's
 * override. Overrides the profile has that this form does not show (a custom
 * formula, tips handling) are kept as they are.
 */
class UserPayFields
{
    public const STREAMER = ['payout_percentage', 'house_cut_percentage', 'hourly_rate'];

    /** Also on the form when a streamer is paid hourly + shipments + profit share. */
    public const SHIFT_SHARE = ['pwe_rate', 'label_rate', 'burden_rate_value'];

    /** The ways a streamer can be paid from this form. */
    public const MODELS = ['profit_share', 'sales_split', 'shift_share'];

    /** Form keys for the business and a streamer's pay model (profit share / sales split). */
    public const BUSINESS = 'pay_business_id';
    public const MODEL = 'pay_model';

    public const FULFILLMENT = ['payout_type', 'hourly_rate', 'pwe_rate', 'label_rate', 'package_rate'];

    /** @return list<string> */
    public static function fieldsFor(string $memberType, ?string $model = null): array
    {
        if ($memberType === 'fulfillment') {
            return self::FULFILLMENT;
        }

        return $model === 'shift_share' ? [...self::STREAMER, ...self::SHIFT_SHARE] : self::STREAMER;
    }

    /** Form state key for a pay field. */
    public static function key(string $field): string
    {
        return 'pay_'.$field;
    }

    /** @return array<string, mixed> form state for a profile (blank where it follows the default) */
    public static function prefill(?Streamer $profile): array
    {
        $state = [];
        foreach (array_unique([...self::STREAMER, ...self::SHIFT_SHARE, ...self::FULFILLMENT]) as $field) {
            $state[self::key($field)] = null;
        }
        $state[self::MODEL] = null;
        $state[self::BUSINESS] = $profile?->business_id ?? BusinessContext::currentId();
        if (! $profile) {
            return $state;
        }

        $resolved = PaymentStructure::resolve($profile);
        foreach (self::fieldsFor($resolved['member_type'], 'shift_share') as $field) {
            $state[self::key($field)] = self::staleLegacyHourly($resolved, $field) ? null : ($resolved['overrides'][$field] ?? null);
        }
        if ($resolved['member_type'] !== 'fulfillment' && ! $resolved['legacy']
            && in_array($resolved['overrides']['payout_type'] ?? null, self::MODELS, true)) {
            $state[self::MODEL] = $resolved['overrides']['payout_type'];
        }

        return $state;
    }

    /** Save the submitted Pay section onto the profile, if it changed. */
    public static function apply(Streamer $profile, array $data): void
    {
        // The business first: it decides which team defaults the blanks follow.
        if (array_key_exists(self::BUSINESS, $data)) {
            $businessId = filled($data[self::BUSINESS]) ? (int) $data[self::BUSINESS] : null;
            if ((int) $profile->business_id !== (int) $businessId) {
                $profile->forceFill(['business_id' => $businessId])->save();
            }
        }

        $resolved = PaymentStructure::resolve($profile);
        $chosen = in_array($data[self::MODEL] ?? null, self::MODELS, true) ? $data[self::MODEL] : null;
        $fields = self::fieldsFor($resolved['member_type'], $chosen ?? self::defaultModel($profile->business_id));

        $submitted = [];
        foreach ($fields as $field) {
            $value = $data[self::key($field)] ?? null;
            $submitted[$field] = $value === '' ? null : $value;
        }

        $model = $resolved['member_type'] !== 'fulfillment' && in_array($data[self::MODEL] ?? null, self::MODELS, true)
            ? $data[self::MODEL]
            : null;

        $before = self::prefill($profile);
        $unchanged = collect($fields)->every(fn ($field) => self::same($before[self::key($field)], $submitted[$field]))
            && ($resolved['member_type'] === 'fulfillment' || self::same($before[self::MODEL], $model));
        if ($unchanged) {
            return;
        }

        // Start from what the person has today. A profile that never adopted
        // the team defaults (legacy) keeps every value it has, so switching it
        // over changes only what was edited here.
        $overrides = $resolved['legacy']
            ? array_filter($resolved['overrides'], fn ($value, $field) => $value !== null && ! self::staleLegacyHourly($resolved, $field), ARRAY_FILTER_USE_BOTH)
            : $resolved['overrides'];

        foreach ($submitted as $field => $value) {
            if ($value === null) {
                unset($overrides[$field]);
            } else {
                $overrides[$field] = $value;
            }
        }

        // A burden set here is a flat $ per show.
        if (isset($overrides['burden_rate_value'])) {
            $overrides['burden_rate_type'] = ($overrides['burden_rate_type'] ?? null) === 'percentage' ? 'percentage' : 'flat';
        } elseif (array_key_exists('burden_rate_value', $submitted)) {
            unset($overrides['burden_rate_type']);
        }

        if ($resolved['member_type'] !== 'fulfillment') {
            // Their own pay type only when chosen (or they have their own
            // formula); otherwise it follows their business's default.
            unset($overrides['payout_type'], $overrides['payout_cadence']);
            if (trim((string) ($overrides['custom_payout_formula'] ?? '')) !== '') {
                $overrides['payout_type'] = 'custom_formula';
            } elseif ($model !== null) {
                $overrides['payout_type'] = $model;
            }
        } else {
            $overrides['payout_cadence'] = 'weekly';
        }

        PaymentStructure::saveOverrides($profile, $overrides);
    }

    /** "Profit share" / "Sales split", the pay model a business's streamers get by default. */
    public static function defaultModel(mixed $businessId): string
    {
        $type = PaymentStructure::defaults('streamer', filled($businessId) ? (int) $businessId : null)['payout_type'] ?? 'profit_share';

        return in_array($type, self::MODELS, true) ? $type : 'profit_share';
    }

    /** "Team default: 8%" for a field's placeholder. */
    public static function defaultHint(string $memberType, string $field, mixed $businessId = null): string
    {
        $value = PaymentStructure::defaults($memberType, filled($businessId) ? (int) $businessId : null)[$field] ?? null;
        if ($value === null || $value === '') {
            return 'Team default: none';
        }

        return 'Team default: '.match ($field) {
            'payout_percentage', 'house_cut_percentage' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%',
            'payout_type' => Streamer::payoutTypeLabels()[$value] ?? $value,
            'hourly_rate' => '$'.number_format((float) $value, 2).'/hr',
            default => '$'.number_format((float) $value, 2),
        };
    }

    /**
     * A streamer's hourly rate on an old-style profile never paid anything
     * (only fulfillment used it), so it is not shown or carried over.
     */
    private static function staleLegacyHourly(array $resolved, string $field): bool
    {
        return $field === 'hourly_rate' && $resolved['legacy'] && $resolved['member_type'] !== 'fulfillment';
    }

    private static function same(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.00001;
        }

        return ($a === null || $a === '') ? ($b === null || $b === '') : (string) $a === (string) $b;
    }
}
