<?php

namespace App\Support;

use App\Models\PayRunType;

/**
 * The pay run types Estill and VCX pay by, made ready to use:
 *
 *   Estill   one weekly run that pays everything (sales splits, hours,
 *            shipments and profit share), plus monthly tips.
 *   VCX      weekly wages, with profit share on its own monthly run.
 *
 * Matched by name, so running this again adds nothing twice.
 */
class BusinessPayRunTypes
{
    /** @var list<array{0:string,1:?string,2:string}> [name, what it pays, frequency] */
    public const PRESETS = [
        ['Estill Weekly', null, 'weekly'],
        ['Estill Monthly Tips', 'tips', 'monthly'],
        ['VCX Weekly Wages', 'wages', 'weekly'],
        ['VCX Monthly Profit Share', 'share', 'monthly'],
    ];

    /** @return int how many types were added */
    public static function ensure(): int
    {
        $added = 0;
        foreach (self::PRESETS as [$name, $kind, $frequency]) {
            if (PayRunType::where('name', $name)->exists()) {
                continue;
            }
            PayRunType::create([
                'name' => $name, 'kind' => $kind, 'active' => true,
                'schedule_enabled' => true, 'frequency' => $frequency, 'auto_create' => false,
            ]);
            $added++;
        }

        return $added;
    }
}
