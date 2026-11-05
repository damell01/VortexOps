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
        $targets = array_map('intval', (array) ($announcement->user_ids ?? []));
        $roles = array_map('intval', (array) ($announcement->role_ids ?? []));
        if ($targets || $roles) {
            $matched = in_array((int) $user->id, $targets, true)
                || ($roles && $user->roles()->whereIn('roles.id', $roles)->exists());
            abort_unless($matched, 403);
        }

        DB::table('team_announcement_acknowledgments')->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'acknowledged_at' => now(),
        ]);

        return response()->json(['acknowledged' => true]);
    }
}
