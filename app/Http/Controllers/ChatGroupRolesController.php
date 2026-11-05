<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class ChatGroupRolesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isOwner()), 403);

        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']);
        $users = User::query()->where('id', '!=', $user->id)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roles->pluck('id')))
            ->with('roles:id,name')
            ->orderBy('name')->limit(500)->get(['id', 'name']);

        return response()->json([
            'roles' => $roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
            'users' => $users->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'role_ids' => $member->roles->pluck('id')->values(),
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
