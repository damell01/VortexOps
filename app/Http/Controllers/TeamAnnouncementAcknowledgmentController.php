<?php

namespace App\Http\Controllers;

use App\Models\TeamAnnouncement;
use App\Services\TeamCommunications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamAnnouncementAcknowledgmentController
{
    public function __invoke(Request $request, TeamAnnouncement $announcement): JsonResponse
    {
        // Keep this endpoint inside the existing owner-only preview until rollout.
        abort_unless(TeamCommunications::canAccess($request->user()), 403);
        abort_unless($announcement->is_preview && $announcement->status === 'published', 404);
        abort_unless($announcement->starts_at && $announcement->starts_at->lte(now()), 404);
        abort_unless(!$announcement->ends_at || $announcement->ends_at->gt(now()), 404);

        $user = $request->user();
        if ($announcement->audience === 'users') {
            abort_unless(in_array((int) $user->id, array_map('intval', (array) $announcement->user_ids), true), 403);
        } elseif ($announcement->audience === 'roles') {
            abort_unless($user->roles()->whereIn('roles.id', (array) $announcement->role_ids)->exists(), 403);
        } elseif ($announcement->audience !== 'all') {
            abort(403);
        }

        DB::table('team_announcement_acknowledgments')->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'acknowledged_at' => now(),
        ]);

        return response()->json(['acknowledged' => true]);
    }
}
