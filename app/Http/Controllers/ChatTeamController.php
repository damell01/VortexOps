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

        // Fetch only a page at a time and search roles as well as names.
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 80);
        $role = strtolower(trim((string) $request->query('role', '')));
        $page = $request->integer('page', 1);
        abort_unless($page >= 1 && $page <= 1000, 422);
        abort_unless(in_array($role, ['', 'fulfillment', 'stream', 'admin'], true), 422);
        $escape = fn (string $value) => addcslashes($value, '%_\\');
        $people = \App\Services\TeamCommunications::previewRecipients(User::query())->whereKeyNot($user->id)
            ->when($search !== '', fn ($query) => $query->where(fn ($matched) => $matched
                ->where('name', 'like', '%'.$escape($search).'%')
                ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'like', '%'.$escape(str_replace(' ', '_', $search)).'%'))))
            ->when($role !== '', fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', 'like', '%'.$role.'%')))
            ->with('roles:id,name')
            ->orderBy('name')->orderBy('id')->offset(($page - 1) * 60)->limit(61)->get(['id', 'name']);
        $hasMore = $people->count() > 60;
        $people = $people->take(60);

        return response()->json([
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
            'data' => $people->map(fn (User $person) => [
                'id' => (string) $person->id,
                'type' => 'user',
                'attributes' => [
                    'name' => $person->name,
                    'role' => $person->roles->pluck('name')->map(fn ($r) => ucwords(str_replace('_', ' ', $r)))->first(),
                    'roles' => $person->roles->pluck('name')->map(fn ($r) => ucwords(str_replace('_', ' ', $r)))->values(),
                ],
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
