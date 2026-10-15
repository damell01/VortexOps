<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The Pokémon that keeps someone company on their time clock (see App\Support\Buddy). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('buddy', 32)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('buddy');
        });
    }
};
