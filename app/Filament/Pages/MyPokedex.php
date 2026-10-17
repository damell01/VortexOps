<?php

namespace App\Filament\Pages;

use App\Models\PokemonCatch;
use App\Models\PokemonTrade;
use App\Models\User;
use App\Support\Pokedex;
use App\Support\PokemonGames;
use App\Support\Trainer;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Anyone's own Pokédex (App\Support\Pokedex, PokemonGames): streamers catch
 * from their reports, everyone catches from their work. Regions, badges,
 * eggs, releasing and trading duplicates all live here.
 * Its own page, so the sprites only load when someone opens it.
 */
class MyPokedex extends Page
{
    protected static ?string $title = 'My Pokédex';
    protected static ?string $slug = 'my-pokedex';
    protected static bool $shouldRegisterNavigation = false;

    public string $tradeMine = '';
    public string $tradeWith = '';
    public string $tradeTheirs = '';

    public function getView(): string { return 'filament.pages.my-pokedex'; }
    public function getSubheading(): ?string
    {
        return auth()->user()?->streamer()->exists()
            ? 'Every approved show report catches a Pokémon, and so does checking in each day. Gotta catch ’em all!'
            : 'Checking in each day catches a Pokémon, and so do paying the team, receiving pallets and packing orders.';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function trainer(): ?Trainer
    {
        return Trainer::forUser(auth()->user());
    }

    // ── Releasing ───────────────────────────────────────────────────────

    public function release(string $species): void
    {
        if (! Pokedex::exists($species) || ! ($trainer = $this->trainer())) {
            return;
        }
        $done = PokemonGames::release($trainer, $species);
        Notification::make()->title($done ? 'Released a ' . Pokedex::name($species) : 'You need two to release one')
            ->body($done ? 'Bye bye! Every ' . PokemonGames::RELEASES_PER_EGG . ' releases earns a Mystery Egg.' : null)
            ->{$done ? 'success' : 'warning'}()->send();
    }

    // ── Trading ─────────────────────────────────────────────────────────

    /** species => count of my duplicates. */
    #[Computed] public function myDuplicates(): Collection
    {
        $t = $this->trainer();

        return $t ? PokemonGames::counts($t)->filter(fn ($n) => $n >= 2)->sortKeys() : collect();
    }

    /** People who could trade: anyone else with a login who has a duplicate. */
    #[Computed] public function tradePartners(): Collection
    {
        $me = auth()->id();
        $ids = PokemonCatch::kept()->selectRaw('coalesce(user_id, 0) as uid, streamer_id, species, count(*) as n')
            ->groupBy('uid', 'streamer_id', 'species')->havingRaw('count(*) >= 2')->get();
        $streamerUsers = \App\Models\Streamer::whereIn('id', $ids->pluck('streamer_id')->filter())->pluck('user_id', 'id');
        $userIds = $ids->map(fn ($r) => $r->streamer_id ? ($streamerUsers[$r->streamer_id] ?? null) : ($r->uid ?: null))->filter()->unique()->reject(fn ($id) => $id === $me);

        return User::whereIn('id', $userIds)->orderBy('name')->pluck('name', 'id');
    }

    #[Computed] public function theirDuplicates(): Collection
    {
        $user = $this->tradeWith !== '' ? User::find((int) $this->tradeWith) : null;

        return $user ? PokemonGames::counts(Trainer::forUser($user))->filter(fn ($n) => $n >= 2)->sortKeys() : collect();
    }

    public function updatedTradeWith(): void
    {
        $this->tradeTheirs = '';
    }

    public function proposeTrade(): void
    {
        $to = User::find((int) $this->tradeWith);
        if (! $to || $this->tradeMine === '' || $this->tradeTheirs === '') {
            Notification::make()->title('Pick your Pokémon, a trainer and theirs')->warning()->send();

            return;
        }
        $result = PokemonGames::proposeTrade(auth()->user(), $this->tradeMine, $to, $this->tradeTheirs);
        if (is_string($result)) {
            Notification::make()->title($result)->warning()->send();

            return;
        }
        $this->reset('tradeMine', 'tradeWith', 'tradeTheirs');
        Notification::make()->title('Trade offered to ' . $to->name)->success()->send();
    }

    public function answerTrade(int $id, bool $accept): void
    {
        $trade = PokemonTrade::find($id);
        if (! $trade) {
            return;
        }
        $message = PokemonGames::answerTrade($trade, auth()->user(), $accept);
        Notification::make()->title($message)->{str_starts_with($message, 'Traded') ? 'success' : 'info'}()->send();
    }

    #[Computed] public function trades(): array
    {
        $me = auth()->id();
        $with = ['proposer:id,name', 'recipient:id,name', 'offered:id,species,shiny', 'requested:id,species,shiny'];

        return [
            'incoming' => PokemonTrade::with($with)->where('recipient_id', $me)->where('status', 'pending')->latest()->get(),
            'outgoing' => PokemonTrade::with($with)->where('proposer_id', $me)->where('status', 'pending')->latest()->get(),
            // Admins see every trade, so a swap is never a mystery.
            'history' => auth()->user()?->isAdmin() || auth()->user()?->isOwner()
                ? PokemonTrade::with($with)->where('status', '!=', 'pending')->latest('responded_at')->limit(15)->get()
                : PokemonTrade::with($with)->where('status', '!=', 'pending')->where(fn ($q) => $q->where('proposer_id', $me)->orWhere('recipient_id', $me))->latest('responded_at')->limit(8)->get(),
        ];
    }

    // ── Eggs ───────────────────────────────────────────────

    #[Computed] public function eggs(): Collection
    {
        $t = $this->trainer();

        return $t ? PokemonGames::eggsFor($t)->with('hatched:id,species,shiny')->latest('id')->limit(8)->get() : collect();
    }

}
