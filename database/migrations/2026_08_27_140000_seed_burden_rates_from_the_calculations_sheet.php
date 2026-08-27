<?php

use App\Models\Setting;
use App\Support\ProfitShareFormula;
use Illuminate\Database\Migrations\Migration;

/**
 * Put the sheet's own burden rates in Settings.
 *
 * The rates used to be constants the code fell back to, so the burden applied
 * whether or not anybody had configured one. Making them optional was right —
 * a charge nobody asked for should not come off somebody's pay — but it left
 * an install that had never opened Settings quietly not charging a burden the
 * Calculations tab has always charged. That is a ~15% overpayment on a show
 * like the signed one, in the streamer's favour and against the paperwork.
 *
 * Seeded only where the row does not exist. A rate somebody has deliberately
 * cleared is a stored empty string, and this leaves it alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seed = [
            'payroll_burden_per_shipment' => ProfitShareFormula::SUGGESTED_PER_SHIPMENT,
            'payroll_burden_per_hour'     => ProfitShareFormula::SUGGESTED_PER_HOUR,
        ];

        foreach ($seed as $key => $value) {
            if (Setting::where('key', $key)->exists()) {
                continue;
            }

            Setting::set($key, number_format($value, 2, '.', ''));
        }
    }

    public function down(): void
    {
        // Deliberately not removed. Deleting these would silently switch the
        // burden back off, which is the state this migration exists to end.
    }
};
