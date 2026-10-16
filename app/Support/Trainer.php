<?php

namespace App\Support;

use App\Models\Streamer;
use App\Models\User;

/**
 * Whoever owns Pokémon: a streamer (their reports catch them), a staff member
 * (their work does), or a streamer with a login (both). Catches are looked up
 * by either id, so nothing a person earned goes missing.
 */
final class Trainer
{
    public function __construct(public readonly ?int $streamerId, public readonly ?int $userId) {}

    public static function forUser(?User $user): ?self
    {
        if (! $user) {
            return null;
        }
        $streamerId = Streamer::where('user_id', $user->id)->value('id');

        return new self($streamerId ? (int) $streamerId : null, (int) $user->id);
    }

    public static function forStreamer(?Streamer $streamer): ?self
    {
        return $streamer ? new self((int) $streamer->id, $streamer->user_id ? (int) $streamer->user_id : null) : null;
    }

    public function key(): string
    {
        return $this->streamerId ? 's' . $this->streamerId : 'u' . $this->userId;
    }

    /** The columns a new catch carries. */
    public function owner(): array
    {
        return ['streamer_id' => $this->streamerId, 'user_id' => $this->userId];
    }

    public function user(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }
}
