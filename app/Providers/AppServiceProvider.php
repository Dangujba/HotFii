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
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Route::model('device', NetworkDevice::class);
    }
}