<?php

namespace App\Rules;

use App\Support\PayoutFormula;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPayoutFormula implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') return;
        try {
            PayoutFormula::validate((string) $value);
        } catch (\RuntimeException $exception) {
            $fail($exception->getMessage());
        }
    }
}
