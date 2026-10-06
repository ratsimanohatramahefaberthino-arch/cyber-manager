<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MikroTikService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MikroTikService::class, function () {
            return new MikroTikService(config('mikrotik'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}