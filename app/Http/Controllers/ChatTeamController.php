<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The teammates you can start a conversation with, for the Messages picker.
 * Anyone who can use team messages may list them; names and roles only.
 */
class ChatTeamController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowed = config('chatify-preview.available', false)
            && (\App\Services\TeamCommunications::canAccess($user) || config('chatify-preview.team_enabled', false));
        abort_unless($user && $allowed, 403);

        $people = User::query()->whereKeyNot($user->id)
            ->with('roles:id,name')
            ->orderBy('name')->limit(500)->get(['id', 'name']);

        return response()->json([
            'data' => $people->map(fn (User $person) => [
                'id' => (string) $person->id,
                'type' => 'user',
                'attributes' => [
                    'name' => $person->name,
                    'role' => $person->roles->pluck('name')->map(fn ($r) => ucwords(str_replace('_', ' ', $r)))->first(),
                ],
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
