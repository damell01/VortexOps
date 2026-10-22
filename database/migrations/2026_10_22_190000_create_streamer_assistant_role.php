<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        Role::findOrCreate('streamer_assistant', 'web');
    }

    public function down(): void
    {
        // Do not delete a role that may have been assigned to employees.
    }
};
