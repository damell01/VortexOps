<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Records a populated "updated" audit entry (old → new + causer) via the
 * activity() helper, which works — unlike the LogsActivity auto-diff on
 * activitylog v5.0.0, which stores empty properties.
 *
 * Use alongside Spatie's LogsActivity (which still handles created/deleted and
 * the activities() relation), and set
 *   protected static array $doNotRecordEvents = ['updated'];
 * on the model so LogsActivity does not also emit an empty "updated" entry.
 *
 * A model may implement `auditableFields(): array` to restrict which changed
 * columns are logged; otherwise every changed column (except timestamps) is.
 */
trait AuditsUpdates
{
    public static function bootAuditsUpdates(): void
    {
        static::updated(function (Model $model): void {
            $fields = method_exists($model, 'auditableFields')
                ? $model->auditableFields()
                : array_keys($model->getChanges());

            $changed = collect($model->getChanges())
                ->only($fields)
                ->except(['created_at', 'updated_at'])
                ->all();

            if (empty($changed)) {
                return;
            }

            $old = [];
            foreach (array_keys($changed) as $key) {
                $old[$key] = $model->getOriginal($key);
            }

            activity(strtolower(class_basename($model)))
                ->performedOn($model)
                ->causedBy(auth()->user())
                ->withProperties(['attributes' => $changed, 'old' => $old])
                ->event('updated')
                ->log('updated');
        });
    }
}
