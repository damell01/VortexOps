<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Throw on lazy-loaded relationships in non-production so N+1 bugs are caught during development.
        Model::preventLazyLoading(! app()->isProduction());
    }
}
