<?php

use App\Support\Pokedex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One Pokémon caught per approved show report; reports already approved get theirs. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pokemon_catches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('streamer_id')->constrained('streamers')->cascadeOnDelete();
            $table->foreignId('streamer_log_entry_id')->unique()->constrained('streamer_log_entries')->cascadeOnDelete();
            $table->foreignId('show_id')->nullable()->constrained('shows')->nullOnDelete();
            $table->string('species', 32);
            $table->boolean('shiny')->default(false);
            $table->timestamp('caught_at')->nullable();
            $table->timestamps();
            $table->index(['streamer_id', 'species']);
        });

        DB::table('streamer_log_entries')->where('status', 'admin_approved')->whereNotNull('streamer_id')
            ->orderBy('id')->select(['id', 'streamer_id', 'show_id', 'reviewed_at', 'updated_at'])
            ->chunk(500, function ($entries) {
                $rows = [];
                foreach ($entries as $e) {
                    [$species, $shiny] = Pokedex::roll((int) $e->id);
                    $at = $e->reviewed_at ?? $e->updated_at ?? now();
                    $rows[] = ['streamer_id' => $e->streamer_id, 'streamer_log_entry_id' => $e->id, 'show_id' => $e->show_id,
                        'species' => $species, 'shiny' => $shiny, 'caught_at' => $at, 'created_at' => now(), 'updated_at' => now()];
                }
                DB::table('pokemon_catches')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pokemon_catches');
    }
};
