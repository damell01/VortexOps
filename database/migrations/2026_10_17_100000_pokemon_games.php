<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Pokémon games (App\Support\PokemonGames): anyone can be a trainer, not
 * just streamers, so a catch belongs to a streamer or a user (or both); each
 * one names what earned it (source) under a key that makes earning it twice
 * impossible. Eggs, trades and evolutions get tables of their own; channels
 * get a mascot and people a birthday.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pokemon_catches', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('streamer_id')->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('report')->after('show_id');
            $table->string('source_key', 120)->nullable()->unique()->after('source');
            $table->string('evolved_from', 32)->nullable()->after('species');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('traded_at')->nullable();
            $table->index(['user_id', 'species']);
        });
        Schema::table('pokemon_catches', function (Blueprint $table) {
            $table->foreignId('streamer_id')->nullable()->change();
            $table->foreignId('streamer_log_entry_id')->nullable()->change();
        });
        $key = DB::getDriverName() === 'sqlite' ? "'report:' || streamer_log_entry_id" : "CONCAT('report:', streamer_log_entry_id)";
        DB::table('pokemon_catches')->whereNull('source_key')->whereNotNull('streamer_log_entry_id')->update(['source_key' => DB::raw($key)]);

        Schema::create('pokemon_evolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokemon_catch_id')->constrained('pokemon_catches')->cascadeOnDelete();
            $table->string('from_species', 32);
            $table->string('to_species', 32);
            $table->string('trigger_key', 120)->unique();
            $table->timestamps();
        });

        Schema::create('pokemon_eggs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('streamer_id')->nullable()->constrained('streamers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('reason', 20);
            $table->string('reason_key', 120)->unique();
            $table->unsignedSmallInteger('steps_needed')->default(3);
            $table->unsignedSmallInteger('steps')->default(0);
            $table->foreignId('hatched_catch_id')->nullable()->constrained('pokemon_catches')->nullOnDelete();
            $table->timestamp('hatched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pokemon_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('offered_catch_id')->constrained('pokemon_catches')->cascadeOnDelete();
            $table->foreignId('requested_catch_id')->constrained('pokemon_catches')->cascadeOnDelete();
            $table->string('status', 12)->default('pending'); // pending | accepted | declined | cancelled | void
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'status']);
        });

        Schema::table('whatnot_channels', function (Blueprint $table) {
            $table->string('mascot', 32)->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->date('birthday')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('birthday'));
        Schema::table('whatnot_channels', fn (Blueprint $t) => $t->dropColumn('mascot'));
        Schema::dropIfExists('pokemon_trades');
        Schema::dropIfExists('pokemon_eggs');
        Schema::dropIfExists('pokemon_evolutions');
        Schema::table('pokemon_catches', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'species']);
            $table->dropUnique(['source_key']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['source', 'source_key', 'evolved_from', 'released_at', 'traded_at']);
        });
    }
};
