<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
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
        return 'Overview of Vortex Breaks operations · ' . now()->format('l, F j, Y');
    }
}
