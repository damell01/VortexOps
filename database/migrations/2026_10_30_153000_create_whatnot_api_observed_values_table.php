<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatnot_api_observed_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->constrained('whatnot_channels')->cascadeOnDelete();
            $table->string('resource', 32);
            $table->string('source_id', 191);
            $table->string('field', 100);
            $table->longText('value_encrypted');
            $table->string('value_sha256', 64);
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->index(['whatnot_channel_id', 'resource', 'source_id', 'field'], 'whatnot_observed_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_api_observed_values');
    }
};
