<?php

namespace App\Providers;

use App\Services\GmcliEnv;
use App\Services\GmcliPaths;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GmcliPaths::class);
        $this->app->singleton(GmcliEnv::class);
    }
}
