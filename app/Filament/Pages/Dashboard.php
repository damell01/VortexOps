<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getView(): string
    {
        if ((bool) Setting::get('demo_mode', false)) {
            return 'filament.pages.demo-dashboard';
        }

        return parent::getView();
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        if ((bool) Setting::get('demo_mode', false)) {
            return 'Welcome to VortexOps';
        }

        $hour = (int) now()->format('G');

        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };

        $name = auth()->user()?->name ?? 'there';

        return "{$greeting}, {$name}";
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        if ((bool) Setting::get('demo_mode', false)) {
            return 'Use the navigation on the left to explore the enabled modules below.';
        }

        return 'Overview of Vortex Breaks operations · ' . now()->format('l, F j, Y');
    }
}
