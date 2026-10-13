<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_run_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('schedule_enabled')->default(false);
            $table->string('frequency')->default('weekly');
            $table->date('anchor')->nullable();
            $table->boolean('auto_create')->default(false);
            $table->timestamps();
        });
        Schema::table('weekly_payout_batches', function (Blueprint $table) {
            $table->foreignId('pay_run_type_id')->nullable()->constrained('pay_run_types')->nullOnDelete();
            $table->string('pay_run_type_name')->nullable();
        });
        foreach ([['Wages', 'wages', 'weekly'], ['Profit share', 'share', 'monthly'], ['Combined pay', null, 'weekly']] as [$name, $kind, $frequency]) {
            DB::table('pay_run_types')->insert(['name' => $name, 'kind' => $kind, 'frequency' => $frequency, 'created_at' => now(), 'updated_at' => now()]);
        }
        // Existing runs and their coverage are deliberately preserved. Old
        // global automation settings no longer control run creation.
    }

    public function down(): void
    {
        Schema::table('weekly_payout_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pay_run_type_id');
            $table->dropColumn('pay_run_type_name');
        });
        Schema::dropIfExists('pay_run_types');
    }
};
