<?php

namespace App\Providers;

use App\Models\DeductionRequest;
use App\Models\Payout;
use App\Observers\DeductionRequestObserver;
use App\Observers\PayoutObserver;
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
        Model::preventLazyLoading(! app()->isProduction());

        Payout::observe(PayoutObserver::class);
        DeductionRequest::observe(DeductionRequestObserver::class);
    }
}
