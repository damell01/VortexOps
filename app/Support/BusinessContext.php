<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Streamer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which business (Vortex Collects, Estill Distribution) an admin is running
 * payroll for, if any. Session-based, like ChannelContext, but it only
 * narrows the payroll pages (see PAYROLL_PAGES): they opt in with the
 * models' inBusinessContext() scopes. Everywhere else shows every business,
 * and the picker and banner are only shown on those pages.
 *
 * Both businesses sell on the same channels, so the line is drawn through
 * people: a team member belongs to one business, and a show belongs to the
 * business of whoever streamed it.
 */
class BusinessContext
{
    private const SESSION_KEY = 'active_business_id';

    /** The pages the business picker applies to: payroll, pay runs, payouts. */
    public const PAYROLL_PAGES = [
        \App\Filament\Pages\PayrollOverview::class,
        \App\Filament\Pages\PaymentStructures::class,
        \App\Filament\Pages\Businesses::class,
        \App\Filament\Pages\PayRunBackfill::class,
        \App\Filament\Resources\WeeklyPayoutBatchResource::class,
        \App\Filament\Resources\PayoutResource::class,
    ];

    private static bool $memoized = false;
    private static ?Business $currentMemo = null;
    private static ?Collection $availableMemo = null;

    /** @return Collection<int, Business> */
    public static function available(): Collection
    {
        return self::$availableMemo ??= Business::ordered();
    }

    public static function current(): ?Business
    {
        if (self::$memoized) {
            return self::$currentMemo;
        }

        $id = app()->runningInConsole() && ! app()->runningUnitTests() ? null : session(self::SESSION_KEY);
        self::$currentMemo = $id ? Business::find($id) : null;
        self::$memoized = true;

        return self::$currentMemo;
    }

    public static function currentId(): ?int
    {
        return self::current()?->id;
    }

    public static function isScoped(): bool
    {
        return self::currentId() !== null;
    }

    /** Whether the page being rendered is one the business picker applies to. */
    public static function appliesToCurrentPage(): bool
    {
        $controller = request()->route()?->getAction('controller');
        $class = is_string($controller) ? explode('@', $controller)[0] : null;
        if (! $class) {
            return false;
        }

        foreach (self::PAYROLL_PAGES as $page) {
            if ($class === $page || str_starts_with($class, $page.'\\')) {
                return true;
            }
        }

        return false;
    }

    public static function setActive(?int $businessId): void
    {
        self::flushMemo();

        if ($businessId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session([self::SESSION_KEY => $businessId]);
    }

    /**
     * Whether anyone has been put in a business yet. Until then payroll runs
     * exactly as it did before businesses existed: one Pay Run per week for
     * everybody.
     */
    public static function inUse(): bool
    {
        return Streamer::query()->whereNotNull('business_id')->exists();
    }

    public static function flushMemo(): void
    {
        self::$memoized = false;
        self::$currentMemo = null;
        self::$availableMemo = null;
    }
}
