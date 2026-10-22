<?php

use App\Models\User;
use App\Services\UserTeamProfileService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        User::query()->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                app(UserTeamProfileService::class)->sync($user);
            }
        });
    }

    public function down(): void
    {
        // Do not remove payroll identities or historical compensation.
    }
};
