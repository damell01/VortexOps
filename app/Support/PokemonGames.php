<?php

namespace App\Support;

use App\Models\FulfillmentPackageItem;
use App\Models\Pallet;
use App\Models\PokemonCatch;
use App\Models\PokemonEgg;
use App\Models\PokemonEvolution;
use App\Models\PokemonTrade;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Notifications\VortexAlert;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Everything you can do with Pokémon beyond the one a report catches
 * (App\Support\Pokedex): regions that open as you fill the last one, evolutions
 * for a run of on-time reports, Mystery Eggs, releasing and trading duplicates,
 * and catches for real work (a record show, a received
 * pallet, packing, approving, paying a run, settling hours).
 *
 * Every catch goes through grant(), under a key that names exactly what
 * earned it, so nothing is ever earned twice however often it is re-run.
 * None of this may stop real work: callers wrap it in rescue().
 */
class PokemonGames
{
    /** A Mystery Egg hatches after this many more catches. */
    public const EGG_STEPS = 3;

    /** Every this many duplicates released earns an egg. */
    public const RELEASES_PER_EGG = 5;

    /** A perfect week: at least this many approved reports, every one on time. */
    public const PERFECT_WEEK_MIN = 3;

    /** What an egg hatches: the whole catalogue, rares and Legendaries a little likelier. */
    public const EGG_WEIGHTS = ['common' => 10, 'uncommon' => 9, 'rare' => 7, 'legendary' => 2];

    // ── Catching ────────────────────────────────────────────────────────

    /**
     * Give the trainer a Pokémon for $key, once. $species fixes it; otherwise
     * it's rolled from $pool (species => weight), or from the regions they've
     * opened. Returns the catch (new or the one already earned for this key).
     */
    public static function grant(Trainer $trainer, string $source, string $key, ?string $species = null, ?array $pool = null, array $extra = []): PokemonCatch
    {
        if ($existing = PokemonCatch::where('source_key', $key)->first()) {
            return $existing;
        }
        $species ??= self::pick($pool ?? self::rollPool($trainer), $source . '|' . $key);
        $catch = PokemonCatch::create($trainer->owner() + $extra + [
            'source' => $source,
            'source_key' => $key,
            'species' => $species,
            'shiny' => crc32('pokedex-shiny|' . $key) % Pokedex::SHINY_ODDS === 0,
            'caught_at' => now(),
        ]);
        if ($source !== 'egg') {
            self::advanceEggs($trainer);
        }

        return $catch;
    }

    /** A weighted pick that is always the same for the same seed. */
    public static function pick(array $weights, string $seed): string
    {
        $weights = array_filter($weights, fn ($w) => $w > 0);
        $pick = crc32('pokemon|' . $seed) % max(1, array_sum($weights));
        foreach ($weights as $species => $weight) {
            $pick -= $weight;
            if ($pick < 0) {
                return $species;
            }
        }

        return array_key_first($weights) ?? 'pikachu';
    }

    /** species => weight for a trainer's everyday roll: the Pokédex in the regions they've opened, plus a running event's. */
    public static function rollPool(Trainer $trainer): array
    {
        $regions = array_flip(self::unlockedRegions($trainer));
        $event = Season::event();
        $details = $event !== null ? (Season::EVENTS[$event] ?? null) : null;
        $boost = array_flip($details['boost'] ?? []);
        $weights = [];
        foreach (Pokedex::DEX as $species => [, $gen, $rarity]) {
            if (isset($regions[$gen])) {
                $weights[$species] = Pokedex::WEIGHTS[$rarity] * (isset($boost[$species]) ? Pokedex::EVENT_BOOST : 1);
            }
        }
        foreach ($details['species'] ?? [] as $species => [, $rarity]) {
            $weights[$species] = Pokedex::WEIGHTS[$rarity];
        }

        return $weights;
    }

    /** @param list<string> $species */
    public static function weighted(array $species, array $weights = Pokedex::WEIGHTS): array
    {
        return collect($species)->mapWithKeys(fn ($s) => [$s => $weights[Pokedex::rarity($s)] ?? 1])->all();
    }

    /** The trainer's kept catches, species => count. */
    public static function counts(Trainer $trainer): Collection
    {
        return PokemonCatch::ownedBy($trainer)->kept()->selectRaw('species, count(*) as n')->groupBy('species')->pluck('n', 'species')->map(fn ($n) => (int) $n);
    }

    // ── Regions ─────────────────────────────────────────────────────────

    /**
     * The regions a trainer can catch from: Kanto, any region they've already
     * caught in, and each next one once they've caught REGION_UNLOCK_SHARE of
     * the one before.
     *
     * @return list<int>
     */
    public static function unlockedRegions(Trainer $trainer, ?Collection $counts = null): array
    {
        $caught = ($counts ?? self::counts($trainer))->keys()->flip();
        $open = [1 => true];
        foreach (Pokedex::DEX as $species => [, $gen]) {
            if (isset($caught[$species])) {
                $open[$gen] = true;
            }
        }
        foreach (array_keys(PokemonData::REGIONS) as $gen) {
            if (isset($open[$gen]) && isset(PokemonData::REGIONS[$gen + 1]) && self::regionShare($gen, $caught) >= PokemonData::REGION_UNLOCK_SHARE) {
                $open[$gen + 1] = true;
            }
        }
        ksort($open);

        return array_keys($open);
    }

    public static function regionShare(int $gen, Collection $caughtFlipped): float
    {
        $species = collect(Pokedex::DEX)->filter(fn ($e) => $e[1] === $gen)->keys();

        return $species->isEmpty() ? 1.0 : $species->filter(fn ($s) => isset($caughtFlipped[$s]))->count() / $species->count();
    }

    // ── Badges ──────────────────────────────────────────────────────────

    /** @return list<array{key:string,label:string,have:int,need:int,earned:bool,sprite:string}> */
    public static function badges(Collection $counts): array
    {
        $byType = [];
        foreach (array_keys(Pokedex::DEX) as $species) {
            foreach (PokemonData::types($species) as $type) {
                $byType[$type][] = $species;
            }
        }
        $badges = [];
        $starters = collect(PokemonData::STARTERS);
        $have = $starters->filter(fn ($s) => ($counts[$s] ?? 0) > 0)->count();
        $badges[] = ['key' => 'starter', 'label' => 'Starter', 'have' => $have, 'need' => $starters->count(), 'earned' => $have === $starters->count(), 'sprite' => 'charmander'];
        ksort($byType);
        foreach ($byType as $type => $species) {
            if (count($species) < PokemonData::BADGE_MIN_SPECIES) {
                continue;
            }
            $have = collect($species)->filter(fn ($s) => ($counts[$s] ?? 0) > 0)->count();
            $badges[] = ['key' => $type, 'label' => ucfirst($type), 'have' => $have, 'need' => count($species), 'earned' => $have === count($species), 'sprite' => $species[0]];
        }

        return $badges;
    }

    // ── On-time streaks and evolution ───────────────────────────────────

    /** Filed by the end of the day after the show (business time). */
    public static function onTime(StreamerLogEntry $report): bool
    {
        $date = $report->loadMissing('show:id,show_date')->show?->show_date;
        if (! $report->submitted_at || ! $date) {
            return false;
        }
        $due = CarbonImmutable::parse($date->toDateString(), BusinessTime::zone())->addDay()->endOfDay();

        return $report->submitted_at->lessThanOrEqualTo($due);
    }

    /** How many approved reports in a row, newest first, were on time. */
    public static function streak(int $streamerId): int
    {
        $reports = StreamerLogEntry::with('show:id,show_date')
            ->where('streamer_id', $streamerId)->where('status', 'admin_approved')->whereNotNull('submitted_at')
            ->latest('submitted_at')->latest('id')->limit(60)->get(['id', 'show_id', 'submitted_at']);
        $n = 0;
        foreach ($reports as $r) {
            if (! self::onTime($r)) {
                break;
            }
            $n++;
        }

        return $n;
    }

    /**
     * Every STREAK_STEP on-time reports in a row, one of the streamer's
     * Pokémon evolves: a first form normally, a middle form at every
     * STREAK_FINAL. Returns [catch, from, to] or null.
     */
    public static function evolveForStreak(StreamerLogEntry $report): ?array
    {
        if (! $report->streamer_id) {
            return null;
        }
        $streak = self::streak((int) $report->streamer_id);
        if ($streak === 0 || $streak % PokemonData::STREAK_STEP !== 0) {
            return null;
        }
        $key = 'evolve:report:' . $report->id;
        if (PokemonEvolution::where('trigger_key', $key)->exists()) {
            return null;
        }
        $trainer = Trainer::forStreamer($report->loadMissing('streamer')->streamer);
        $candidates = PokemonCatch::ownedBy($trainer)->kept()->whereIn('species', array_keys(PokemonData::EVOLUTIONS))
            ->oldest('caught_at')->oldest('id')->get();
        if ($candidates->isEmpty()) {
            return null;
        }
        $preferred = $streak % PokemonData::STREAK_FINAL === 0 ? 2 : 1;
        $catch = $candidates->first(fn ($c) => PokemonData::stage($c->species) === $preferred) ?? $candidates->first();
        $options = PokemonData::EVOLUTIONS[$catch->species];
        $to = $options[crc32($key) % count($options)];
        $from = $catch->species;

        DB::transaction(function () use ($catch, $from, $to, $key) {
            $catch->update(['species' => $to, 'evolved_from' => $from]);
            PokemonEvolution::create(['pokemon_catch_id' => $catch->id, 'from_species' => $from, 'to_species' => $to, 'trigger_key' => $key]);
        });

        return [$catch, $from, $to];
    }

    // ── Eggs ────────────────────────────────────────────────────────────

    /** A perfect week (the one before this report's) earns an egg. */
    public static function perfectWeekEgg(StreamerLogEntry $report): ?PokemonEgg
    {
        if (! $report->streamer_id || ! $report->submitted_at) {
            return null;
        }
        $week = CarbonImmutable::parse($report->submitted_at)->setTimezone(BusinessTime::zone())->startOfWeek()->subWeek();
        $reports = StreamerLogEntry::with('show:id,show_date')->where('streamer_id', $report->streamer_id)
            ->whereHas('show', fn ($q) => $q->whereBetween('show_date', [$week->toDateString(), $week->endOfWeek()->toDateString()]))
            ->get(['id', 'show_id', 'status', 'submitted_at']);
        if ($reports->count() < self::PERFECT_WEEK_MIN || $reports->contains(fn ($r) => $r->status !== 'admin_approved' || ! self::onTime($r))) {
            return null;
        }
        $trainer = Trainer::forStreamer($report->loadMissing('streamer')->streamer);

        return PokemonEgg::firstOrCreate(
            ['reason_key' => 'egg:week:' . $report->streamer_id . ':' . $week->format('o-W')],
            $trainer->owner() + ['reason' => 'perfect_week', 'steps_needed' => self::EGG_STEPS],
        );
    }

    /** One step on the trainer's oldest egg; it hatches when it has enough. */
    public static function advanceEggs(Trainer $trainer): ?PokemonCatch
    {
        $egg = self::eggsFor($trainer)->whereNull('hatched_at')->oldest('id')->first();
        if (! $egg) {
            return null;
        }
        $egg->increment('steps');
        if ($egg->steps < $egg->steps_needed) {
            return null;
        }
        $all = array_merge(array_keys(Pokedex::DEX), array_keys(PokemonData::EXTRA));
        $catch = self::grant($trainer, 'egg', 'egg:hatch:' . $egg->id, null, self::weighted($all, self::EGG_WEIGHTS));
        $egg->update(['hatched_catch_id' => $catch->id, 'hatched_at' => now()]);

        return $catch;
    }

    public static function eggsFor(Trainer $trainer)
    {
        return PokemonEgg::query()->where(function ($w) use ($trainer) {
            $trainer->streamerId ? $w->where('streamer_id', $trainer->streamerId) : $w->whereRaw('1 = 0');
            if ($trainer->userId) {
                $w->orWhere('user_id', $trainer->userId);
            }
        });
    }

    // ── Releasing ───────────────────────────────────────────────────────

    /**
     * Let one duplicate go (never the last of a species). Every
     * RELEASES_PER_EGG releases earns an egg. Returns false when there's no
     * duplicate to release.
     */
    public static function release(Trainer $trainer, string $species): bool
    {
        $copies = PokemonCatch::ownedBy($trainer)->kept()->where('species', $species)
            ->orderBy('shiny')->latest('caught_at')->latest('id')->get();
        if ($copies->count() < 2 || self::inPendingTrade($copies->first()->id)) {
            return false;
        }
        $copies->first()->update(['released_at' => now()]);
        $released = PokemonCatch::ownedBy($trainer)->whereNotNull('released_at')->count();
        if ($released % self::RELEASES_PER_EGG === 0) {
            PokemonEgg::firstOrCreate(
                ['reason_key' => 'egg:release:' . $trainer->key() . ':' . intdiv($released, self::RELEASES_PER_EGG)],
                $trainer->owner() + ['reason' => 'releases', 'steps_needed' => self::EGG_STEPS],
            );
        }

        return true;
    }

    // ── Trading ─────────────────────────────────────────────────────────

    /** A duplicate a trainer can offer: one of two or more, not shiny if a plain one will do, not already offered. */
    public static function spareCatch(Trainer $trainer, string $species): ?PokemonCatch
    {
        $copies = PokemonCatch::ownedBy($trainer)->kept()->where('species', $species)->orderBy('shiny')->latest('caught_at')->get();

        return $copies->count() >= 2 ? $copies->first(fn ($c) => ! self::inPendingTrade($c->id)) : null;
    }

    public static function inPendingTrade(int $catchId): bool
    {
        return PokemonTrade::where('status', 'pending')->where(fn ($q) => $q->where('offered_catch_id', $catchId)->orWhere('requested_catch_id', $catchId))->exists();
    }

    /** Offer one of my duplicates for one of theirs. Null (with a reason) when either side has no spare. */
    public static function proposeTrade(User $from, string $mySpecies, User $to, string $theirSpecies): PokemonTrade|string
    {
        if ($from->is($to)) {
            return 'You can’t trade with yourself.';
        }
        $mine = self::spareCatch(Trainer::forUser($from), $mySpecies);
        $theirs = self::spareCatch(Trainer::forUser($to), $theirSpecies);
        if (! $mine) {
            return 'You need two of a Pokémon to trade one away.';
        }
        if (! $theirs) {
            return $to->name . ' doesn’t have a spare ' . Pokedex::name($theirSpecies) . '.';
        }
        $trade = PokemonTrade::create(['proposer_id' => $from->id, 'recipient_id' => $to->id, 'offered_catch_id' => $mine->id, 'requested_catch_id' => $theirs->id, 'status' => 'pending']);
        rescue(fn () => $to->notify(new VortexAlert('pokemon_trade', 'Pokémon trade offer',
            "{$from->name} offers their " . Pokedex::name($mySpecies) . ' for your ' . Pokedex::name($theirSpecies) . '.', 'info',
            ['Open my Pokédex' => \App\Filament\Pages\MyPokedex::getUrl()])), null, false);

        return $trade;
    }

    /** The recipient answers. Accepting swaps the two catches, as long as both are still spare. */
    public static function answerTrade(PokemonTrade $trade, User $by, bool $accept): string
    {
        if ($trade->status !== 'pending') {
            return 'That trade was already settled.';
        }
        if ($trade->proposer_id === $by->id && ! $accept) {
            $trade->update(['status' => 'cancelled', 'responded_at' => now()]);

            return 'Trade cancelled.';
        }
        if ($trade->recipient_id !== $by->id) {
            return 'Only the person it was offered to can answer it.';
        }
        if (! $accept) {
            $trade->update(['status' => 'declined', 'responded_at' => now()]);
            self::tell($trade->proposer, 'Trade declined', "{$by->name} declined your trade.");

            return 'Trade declined.';
        }

        return DB::transaction(function () use ($trade, $by) {
            $offered = PokemonCatch::lockForUpdate()->find($trade->offered_catch_id);
            $requested = PokemonCatch::lockForUpdate()->find($trade->requested_catch_id);
            $proposer = Trainer::forUser($trade->proposer);
            $recipient = Trainer::forUser($by);
            $stillSpare = fn (?PokemonCatch $c, Trainer $t) => $c && ! $c->released_at
                && PokemonCatch::ownedBy($t)->kept()->whereKey($c->id)->exists()
                && PokemonCatch::ownedBy($t)->kept()->where('species', $c->species)->count() >= 2;
            if (! $stillSpare($offered, $proposer) || ! $stillSpare($requested, $recipient)) {
                $trade->update(['status' => 'void', 'responded_at' => now()]);

                return 'One of those Pokémon isn’t spare any more, so the trade was called off.';
            }
            $offered->update($recipient->owner() + ['traded_at' => now()]);
            $requested->update($proposer->owner() + ['traded_at' => now()]);
            $trade->update(['status' => 'accepted', 'responded_at' => now()]);
            self::tell($trade->proposer, 'Trade complete!', "{$by->name} accepted: you got their " . Pokedex::name($requested->species) . '.');

            return 'Traded! You got ' . Pokedex::name($offered->species) . '.';
        });
    }

    private static function tell(?User $user, string $title, string $body): void
    {
        if ($user) {
            rescue(fn () => $user->notify(new VortexAlert('pokemon_trade', $title, $body, 'success', ['Open my Pokédex' => \App\Filament\Pages\MyPokedex::getUrl()])), null, false);
        }
    }

    // ── Catches for real work ───────────────────────────────────────────

    /** A record show: a wild Pokémon turns up for its streamer. */
    public static function encounter(StreamerLogEntry $report): ?PokemonCatch
    {
        $trainer = Trainer::forStreamer($report->loadMissing('streamer')->streamer);

        return $trainer && $report->show_id ? self::grant($trainer, 'encounter', 'encounter:show:' . $report->show_id) : null;
    }

    /** A fully received pallet: a Safari Zone catch for whoever received it. */
    public static function palletReceived(Pallet $pallet, ?User $by): ?PokemonCatch
    {
        $trainer = Trainer::forUser($by);

        return $trainer ? self::grant($trainer, 'safari', 'safari:pallet:' . $pallet->id) : null;
    }

    /** Every DELIVERY_EVERY items someone packs, a delivery bird. */
    public static function itemPacked(FulfillmentPackageItem $item): ?PokemonCatch
    {
        if (! $item->packed_by || ! $item->packed_at) {
            return null;
        }
        $count = FulfillmentPackageItem::where('packed_by', $item->packed_by)->whereNotNull('packed_at')->count();
        if ($count === 0 || $count % PokemonData::DELIVERY_EVERY !== 0) {
            return null;
        }
        $trainer = Trainer::forUser(User::find($item->packed_by));

        return $trainer ? self::grant($trainer, 'delivery', 'delivery:' . $item->packed_by . ':' . intdiv($count, PokemonData::DELIVERY_EVERY), null, self::weighted(PokemonData::DELIVERY)) : null;
    }

    /** Every APPROVALS_EVERY reports an admin approves. */
    public static function reportApproved(StreamerLogEntry $report): ?PokemonCatch
    {
        if (! $report->reviewed_by) {
            return null;
        }
        $count = StreamerLogEntry::where('reviewed_by', $report->reviewed_by)->where('status', 'admin_approved')->count();
        if ($count === 0 || $count % PokemonData::APPROVALS_EVERY !== 0) {
            return null;
        }
        $trainer = Trainer::forUser(User::find($report->reviewed_by));

        return $trainer ? self::grant($trainer, 'approvals', 'approvals:' . $report->reviewed_by . ':' . intdiv($count, PokemonData::APPROVALS_EVERY)) : null;
    }

    /** Every APPROVALS_EVERY hours issues an admin settles. */
    public static function hoursSettled(StreamerLogEntry $report): ?PokemonCatch
    {
        if (! $report->hours_reviewed_by) {
            return null;
        }
        $count = StreamerLogEntry::where('hours_reviewed_by', $report->hours_reviewed_by)->whereNotNull('hours_review')->count();
        if ($count === 0 || $count % PokemonData::HOURS_EVERY !== 0) {
            return null;
        }
        $trainer = Trainer::forUser(User::find($report->hours_reviewed_by));

        return $trainer ? self::grant($trainer, 'hours', 'hours:' . $report->hours_reviewed_by . ':' . intdiv($count, PokemonData::HOURS_EVERY)) : null;
    }

    /** Marking a pay run paid. */
    public static function payRunPaid(WeeklyPayoutBatch $batch, ?User $by): ?PokemonCatch
    {
        $trainer = Trainer::forUser($by);

        return $trainer ? self::grant($trainer, 'payrun', 'payrun:' . $batch->id) : null;
    }

    /** After a report's own catch: an evolution for the streak, and a perfect-week egg. */
    public static function afterReport(StreamerLogEntry $report): array
    {
        $evolved = rescue(fn () => self::evolveForStreak($report), null, true);
        $egg = rescue(fn () => self::perfectWeekEgg($report), null, true);

        return ['evolved' => $evolved, 'egg' => $egg];
    }

    /** Show the catch on the next page for whoever is signed in, if it's theirs. */
    public static function flashFor(?PokemonCatch $catch, string $note = ''): void
    {
        if (! $catch || ! $catch->wasRecentlyCreated || ! auth()->check() || ! app()->bound('session')) {
            return;
        }
        $mine = $catch->user_id === auth()->id() || ($catch->streamer_id && $catch->streamer_id === auth()->user()?->streamer()->value('id'));
        if ($mine) {
            session()->flash('vx_caught', ['species' => $catch->species, 'shiny' => $catch->shiny, 'rarity' => Pokedex::rarity($catch->species), 'note' => $note]);
        }
    }

    /** What earned a catch, in words. */
    public static function sourceLabel(string $source): string
    {
        return [
            'report' => 'Show report', 'encounter' => 'Record show', 'safari' => 'Pallet received', 'delivery' => 'Packing',
            'approvals' => 'Approving reports', 'hours' => 'Settling hours', 'payrun' => 'Paying a pay run', 'egg' => 'Hatched from an egg',
        ][$source] ?? ucfirst($source);
    }

    /** Share of everyday catches that are this species, and how many trainers have one. */
    public static function rarityStats(): array
    {
        return Cache::remember('pokemon-rarity-stats', 600, function () {
            $total = array_sum(array_map(fn ($e) => Pokedex::WEIGHTS[$e[2]], Pokedex::DEX));
            // Streamers by streamer, everyone else by user; works the same on MySQL and SQLite.
            $byStreamer = PokemonCatch::kept()->whereNotNull('streamer_id')->selectRaw('species, count(distinct streamer_id) as n')->groupBy('species')->pluck('n', 'species');
            $byUser = PokemonCatch::kept()->whereNull('streamer_id')->whereNotNull('user_id')->selectRaw('species, count(distinct user_id) as n')->groupBy('species')->pluck('n', 'species');
            $owners = $byStreamer->keys()->merge($byUser->keys())->unique()->mapWithKeys(fn ($s) => [$s => (int) ($byStreamer[$s] ?? 0) + (int) ($byUser[$s] ?? 0)]);

            return collect(Pokedex::DEX)->map(fn ($e, $s) => ['pct' => round(Pokedex::WEIGHTS[$e[2]] / $total * 100, 1), 'owners' => (int) ($owners[$s] ?? 0)])
                ->union(collect(PokemonData::EXTRA)->map(fn ($e, $s) => ['pct' => null, 'owners' => (int) ($owners[$s] ?? 0)]))
                ->all();
        });
    }
}
