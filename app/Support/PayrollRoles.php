<?php

namespace App\Support;

use App\Models\Streamer;

/**
 * The roles a pay run is split into, in the order they are listed.
 *
 * A payroll profile's member_type says which: streamers, their assistants,
 * fulfillment, and anyone else paid through payroll. A profile with no type
 * predates the column and is a streamer; an unrecognised one is "other", so
 * nobody drops out of a run because their role is new.
 */
final class PayrollRoles
{
    public const ORDER = ['streamer', 'assistant', 'fulfillment', 'other'];

    public static function labels(): array
    {
        return ['streamer' => 'Streamer', 'assistant' => 'Streamer assistant', 'fulfillment' => 'Fulfillment', 'other' => 'Other'];
    }

    public static function plural(string $role): string
    {
        return ['streamer' => 'Streamers', 'assistant' => 'Streamer assistants', 'fulfillment' => 'Fulfillment', 'other' => 'Other team members'][$role] ?? 'Other team members';
    }

    public static function of(?Streamer $person): string
    {
        $type = $person?->member_type ?: 'streamer';

        return in_array($type, self::ORDER, true) ? $type : 'other';
    }

    public static function rank(string $role): int
    {
        $i = array_search($role, self::ORDER, true);

        return $i === false ? count(self::ORDER) : $i;
    }
}
