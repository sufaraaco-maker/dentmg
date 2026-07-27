<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        // Keep API responses flat (no "data" envelope) to match the rest of the API.
        JsonResource::withoutWrapping();

        // Baseline throttle for every api/* route (bootstrap/app.php wires this in via
        // throttleApi()). /login has its own tighter, purpose-built limiter
        // (see AuthService::MAX_ATTEMPTS) — this is the general-purpose backstop for
        // everything else (patients, users, appointments, etc.), which previously had none.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config('api.throttle_per_minute'))->by(
            $request->user()?->id ?: $request->ip()
        ));
    }
}
