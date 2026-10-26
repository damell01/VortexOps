<?php

use App\Support\AssistantPayrollRole;
use App\Support\NavVisibility;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        NavVisibility::flushMemo();
        $role = AssistantPayrollRole::NAME;
        $pages = [
            \App\Filament\Resources\ShowResource::class,
            \App\Filament\Pages\ShowLeaderboard::class,
            \App\Filament\Pages\MyPay::class,
            \App\Filament\Pages\Timekeeping::class,
        ];
        // Enable the requested workspace on saved roles as well as fresh installations.
        // Keep every unrelated page and every other role exactly as configured.
        if (NavVisibility::hasExplicitVisibility($role)) {
            NavVisibility::setVisibleForRole($role, array_merge(NavVisibility::visibleForRole($role), $pages));
        }
        NavVisibility::setHiddenForRole($role, array_values(array_diff(NavVisibility::hiddenForRole($role), $pages)));
    }

    public function down(): void
    {
        // Do not undo permission choices the owner may have made since rollout.
    }
};
