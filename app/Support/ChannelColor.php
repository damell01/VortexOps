<?php

namespace App\Support;

/**
 * A steady accent per Whatnot channel, from the Vortex purple/blue family,
 * so a channel reads the same colour everywhere it appears.
 */
class ChannelColor
{
    public const PALETTE = ['#7c3aed', '#2563eb', '#c026d3', '#0891b2', '#4f46e5', '#9333ea', '#0284c7', '#a21caf'];

    public static function for(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));

        return $name === '' ? '#94a3b8' : self::PALETTE[crc32($name) % count(self::PALETTE)];
    }
}
