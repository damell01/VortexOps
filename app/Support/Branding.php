<?php

namespace App\Support;

class Branding
{
    public const DEFAULT_NAME = 'Vortex Breaks';

    public const DEFAULT_PRIMARY_COLOR = '#29E7E7';

    public const DEFAULT_LOGO_ASSET = 'images/vortexbreaks-logo.webp';

    /**
     * @return array<int, array{label: string, hex: string}>
     */
    public static function presets(): array
    {
        return [
            ['label' => 'Vortex Aqua', 'hex' => '#29E7E7'],
            ['label' => 'Deep Violet', 'hex' => '#6D28D9'],
            ['label' => 'Electric Blue', 'hex' => '#2563EB'],
            ['label' => 'Indigo', 'hex' => '#4338CA'],
            ['label' => 'Rose', 'hex' => '#E11D48'],
            ['label' => 'Emerald', 'hex' => '#059669'],
            ['label' => 'Amber', 'hex' => '#D97706'],
            ['label' => 'Slate', 'hex' => '#475569'],
        ];
    }
}
