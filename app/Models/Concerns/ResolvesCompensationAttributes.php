<?php

namespace App\Models\Concerns;

use App\Support\PaymentStructure;

/**
 * Makes the existing payout engine read effective role defaults + individual
 * overrides without creating a second calculation path.
 *
 * PaymentStructure itself reads raw model values, so these accessors cannot
 * recurse back into the resolver.
 */
trait ResolvesCompensationAttributes
{
    private function resolvedCompensationAttribute(string $field, mixed $raw): mixed
    {
        if (! $this->exists) {
            return $raw;
        }

        return PaymentStructure::effective($this, $field, $raw);
    }

    public function getPayoutTypeAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('payout_type', $value);
    }

    public function getPayoutCadenceAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('payout_cadence', $value);
    }

    public function getPayoutPercentageAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('payout_percentage', $value);
    }

    public function getPackageRateAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('package_rate', $value);
    }

    public function getHourlyRateAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('hourly_rate', $value);
    }

    public function getPweRateAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('pwe_rate', $value);
    }

    public function getLabelRateAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('label_rate', $value);
    }

    public function getIncludeTipsAttribute(mixed $value): bool
    {
        return (bool) $this->resolvedCompensationAttribute('include_tips', $value);
    }

    public function getCustomPayoutFormulaAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('custom_payout_formula', $value);
    }

    public function getBurdenRateTypeAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('burden_rate_type', $value);
    }

    public function getBurdenRateValueAttribute(mixed $value): mixed
    {
        return $this->resolvedCompensationAttribute('burden_rate_value', $value);
    }
}
