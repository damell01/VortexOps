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
    public static function register(): void
    {
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
