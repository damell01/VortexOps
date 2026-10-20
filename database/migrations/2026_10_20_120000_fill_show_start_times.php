<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Most Seller Hub imports read "Time TBD": the time was in the row's text but
 * never saved. Fill it once for every show already imported; new imports read
 * it themselves (WhatnotReportingReconciler). Re-runnable by hand with
 * php artisan shows:fill-start-times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('shows:fill-start-times');
    }

    public function down(): void
    {
        // Times read from the import stay: they are what Whatnot showed.
    }
};
