<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Models\ShowChangeLog;

class ShipmentObserver
{
    /**
     * Log meaningful Whatnot shipment changes against the parent show's existing
     * Change History. Initial shipment creation is intentionally not logged here;
     * the audit trail is for later changes after the first import.
     */
    public function updating(Shipment $shipment): void
    {
        $show = $shipment->show;
        if (! $show) {
            return;
        }

        $fields = [
            'buyer_username',
            'item_count',
            'shipping_cost',
            'weight_oz',
            'dimensions_json',
            'status',
            'carrier',
            'tracking_number',
            'insurance_added',
            'signature_required',
        ];

        $tracking = $shipment->tracking_number ?: $shipment->getOriginal('tracking_number') ?: 'untracked shipment';

        foreach ($fields as $field) {
            if (! $shipment->isDirty($field)) {
                continue;
            }

            $old = $shipment->getOriginal($field);
            $new = $shipment->getAttribute($field);

            $format = static function ($value): string {
                if ($value === null || $value === '') {
                    return '—';
                }
                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }
                if (is_array($value)) {
                    return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
                }
                return (string) $value;
            };

            $oldText = $format($old);
            $newText = $format($new);
            if ($oldText === $newText) {
                continue;
            }

            ShowChangeLog::logChange(
                $show,
                'shipment_' . $field,
                "{$tracking}: {$oldText}",
                "{$tracking}: {$newText}",
                'whatnot_shipment_import',
            );
        }
    }
}
