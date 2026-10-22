<?php

use App\Models\User;
use App\Services\UserTeamProfileService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        User::role('streamer_assistant')->with('streamer')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                app(UserTeamProfileService::class)->sync($user);
            }
        });
    }

    public function down(): void
    {
        // Profiles may contain payroll history; never remove them on rollback.
    }
};
