<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Laravel's ApplicationBuilder registers a default guest-redirect to route('login')
        // before this callback runs. This app has no 'login' web route (auth is SPA/API-only),
        // so that default crashes with a RouteNotFoundException for any unauthenticated request
        // that doesn't send an Accept: application/json header. Returning null here lets the
        // request fall through to the JSON 401 handled below instead of crashing.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only app: requests to /api/* must always get a JSON error response, even from
        // clients that omit an Accept: application/json header (browsers, curl, misconfigured
        // clients), not just axios/fetch which set it automatically.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
