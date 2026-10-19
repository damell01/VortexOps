<?php

namespace App\Support;

use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * A value a record already has is always valid to keep. A form's rules describe
 * what can be entered now, and a record can hold something older that no longer
 * fits (a channel since switched off, a negative unit count Whatnot reported).
 * Failing it blocked every save of that record, even one that only changed a
 * different field. Anything newly typed or picked still has to pass.
 */
class SavedValue
{
    public static function unchanged(Field $component): bool
    {
        $state = $component->getState();
        $record = $component->getRecord();
        if (blank($state) || is_array($state) || ! $record instanceof Model || ! $record->exists) {
            return false;
        }
        $name = $component->getName();
        if (! array_key_exists($name, $record->getAttributes())) {
            return false;
        }
        $saved = $record->getOriginal($name);
        $saved = $saved instanceof \BackedEnum ? $saved->value : $saved;
        $value = $state instanceof \BackedEnum ? $state->value : $state;

        return is_numeric($saved) && is_numeric($value)
            ? (float) $saved === (float) $value
            : (string) $saved === (string) $value;
    }

    /** A min/max limit that steps aside while the field still holds its saved value. */
    public static function limit(int|float $limit): \Closure
    {
        return fn (Field $component) => self::unchanged($component) ? null : $limit;
    }
}
