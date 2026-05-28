<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Support\DemoDataManager;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('vortex:demo-seed', function (DemoDataManager $manager) {
    $result = $manager->seed();

    $this->info($result['message']);

    foreach ($result['details'] as $line) {
        $this->line('- ' . $line);
    }
})->purpose('Refresh the optional demo data set for testing shows, deductions, and inventory flows.');

Artisan::command('vortex:demo-clear', function (DemoDataManager $manager) {
    $result = $manager->clear();

    $this->info('Demo data cleared.');
    $this->line('- ' . $result['summary']);
})->purpose('Remove the optional demo data set from the application.');
