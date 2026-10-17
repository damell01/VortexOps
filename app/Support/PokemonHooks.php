<?php

namespace App\Support;

use App\Models\FulfillmentPackageItem;
use App\Models\Pallet;
use App\Models\StreamerLogEntry;
use App\Models\WeeklyPayoutBatch;
use App\Notifications\VortexAlert;

/**
 * Where real work earns Pokémon (App\Support\PokemonGames), as model events
 * so the services doing the work don't need to know about any of it.
 * Every hook is wrapped: a Pokémon must never stop real work.
 */
class PokemonHooks
{
    /** Who has already been checked for today's catch in this request. */
    private static array $dailySeen = [];

    public static function register(): void
    {
        self::$dailySeen = [];
        // A daily check-in also counts a save, for work done without a full page load.
        \Illuminate\Support\Facades\Event::listen('eloquent.saved: *', function (string $event, array $models) {
            $model = $models[0] ?? null;
            if (! auth()->check() || ! $model || $model instanceof \App\Models\User || $model instanceof \Illuminate\Notifications\DatabaseNotification
                || str_starts_with($model::class, 'App\\Models\\Pokemon') || $model instanceof \App\Models\WebPushSubscription) {
                return;
            }
            PokemonGames::flashFor(self::checkIn(auth()->user()), 'Daily check-in');
        });

        StreamerLogEntry::saved(function (StreamerLogEntry $report) {
            if ($report->wasChanged('status') && $report->status === 'admin_approved') {
                self::safely(function () use ($report) {
                    $after = PokemonGames::afterReport($report);
                    self::announce($report, $after);
                    PokemonGames::flashFor(PokemonGames::reportApproved($report), 'For approving reports');
                });
            }
            if ($report->wasChanged('hours_reviewed_by') && $report->hours_reviewed_by) {
                self::safely(fn () => PokemonGames::flashFor(PokemonGames::hoursSettled($report), 'For settling hours'));
            }
        });

        Pallet::saved(function (Pallet $pallet) {
            if ($pallet->wasChanged('status') && $pallet->status === 'received') {
                self::safely(fn () => PokemonGames::flashFor(PokemonGames::palletReceived($pallet, auth()->user()), 'Safari Zone: a pallet received!'));
            }
        });

        FulfillmentPackageItem::saved(function (FulfillmentPackageItem $item) {
            if ($item->wasChanged('packed_at') && $item->packed_at) {
                self::safely(fn () => PokemonGames::flashFor(PokemonGames::itemPacked($item), 'A delivery Pokémon, for packing'));
            }
        });

        WeeklyPayoutBatch::saved(function (WeeklyPayoutBatch $batch) {
            if ($batch->wasChanged('status') && $batch->status === 'paid') {
                self::safely(fn () => PokemonGames::flashFor(PokemonGames::payRunPaid($batch, auth()->user()), 'For paying the team'));
            }
        });
    }

    /**
     * The first time today this person opens a page or saves anything: their
     * daily catch. Checked at most once per person per day (cache), so an
     * ordinary page or save costs nothing extra. Null when already caught.
     */
    public static function checkIn(?\App\Models\User $user): ?\App\Models\PokemonCatch
    {
        if (! $user) {
            return null;
        }
        $day = now(BusinessTime::zone())->toDateString();
        if (isset(self::$dailySeen[$user->id . $day])) {
            return null;
        }
        self::$dailySeen[$user->id . $day] = true;
        if (! \Illuminate\Support\Facades\Cache::add("pokemon-daily:{$user->id}:{$day}", 1, now()->addDay())) {
            return null;
        }
        $catch = null;
        self::safely(function () use ($user, &$catch) { $catch = PokemonGames::dailyActivity($user); });

        return $catch?->wasRecentlyCreated ? $catch : null;
    }

    /** The catch reveal for a page that is rendering now (delight.js plays it). */
    public static function revealHtml(\App\Models\PokemonCatch $catch, string $note): string
    {
        return '<div data-vx-caught="' . e($catch->species) . '" data-shiny="' . ($catch->shiny ? '1' : '0') . '" data-rarity="' . e(Pokedex::rarity($catch->species))
            . '" data-name="' . e(Pokedex::name($catch->species)) . '" data-note="' . e($note) . '" hidden></div>';
    }

    /** Tell the streamer about an evolution or a new egg. */
    private static function announce(StreamerLogEntry $report, array $after): void
    {
        $user = $report->loadMissing('streamer.user')->streamer?->user;
        if (! $user) {
            return;
        }
        if ($after['evolved']) {
            [, $from, $to] = $after['evolved'];
            $user->notify(new VortexAlert('pokemon', 'What? ' . Pokedex::name($from) . ' is evolving!',
                'Your ' . Pokedex::name($from) . ' evolved into ' . Pokedex::name($to) . ' for ' . PokemonData::STREAK_STEP . ' on-time reports in a row.', 'success',
                ['Open my Pokédex' => \App\Filament\Pages\MyPokedex::getUrl()], extra: ['species' => $to]));
        }
        if ($after['egg']?->wasRecentlyCreated) {
            $user->notify(new VortexAlert('pokemon', 'You found a Mystery Egg!',
                'A perfect week of on-time reports. It hatches after ' . PokemonGames::EGG_STEPS . ' more catches.', 'success',
                ['Open my Pokédex' => \App\Filament\Pages\MyPokedex::getUrl()]));
        }
    }

    private static function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
