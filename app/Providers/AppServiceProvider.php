<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('inicio-sesion', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('contexto-sesion', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
