<?php

namespace App\Providers;

use App\Models\NetworkDevice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        // Unconditional, production included. Left environment-dependent, a
        // write that names a non-fillable column throws here and is discarded
        // in silence on the live site — which is how approved organizations
        // ended up Live, holding a Paystack subaccount code, and unable to take
        // a payment. On a deployment handling other people's money, one visible
        // error beats data quietly going wrong for weeks.
        Model::preventSilentlyDiscardingAttributes();
        Route::model('device', NetworkDevice::class);
    }
}