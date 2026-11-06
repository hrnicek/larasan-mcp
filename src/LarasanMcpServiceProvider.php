<?php

declare(strict_types=1);

namespace Larasan\Mcp;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Larasan\Mcp\Servers\LarasanServer;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;

final class LarasanMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/larasan-mcp.php', 'larasan-mcp');

        // MCP clients authenticate with Passport access tokens; an application that defines its own `api` guard keeps it.
        if (config('auth.guards.api') === null) {
            config(['auth.guards.api' => ['driver' => 'passport', 'provider' => 'users']]);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/larasan-mcp.php' => config_path('larasan-mcp.php'),
        ], 'larasan-mcp-config');

        RateLimiter::for('larasan-mcp', fn (Request $request): Limit => Limit::perMinute(
            (int) config('larasan-mcp.requests_per_minute'),
        )->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('larasan-mcp-oauth', fn (Request $request): Limit => Limit::perMinute(
            (int) config('larasan-mcp.oauth.registrations_per_minute'),
        )->by((string) $request->ip()));

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'larasan-mcp');

        // laravel/mcp ships `redirect_domains => ['*']`; that default is replaced, a published list is kept.
        if (config('mcp.redirect_domains') === ['*']) {
            config([
                'mcp.redirect_domains' => config('larasan-mcp.oauth.redirect_domains'),
                'mcp.custom_schemes' => config('mcp.custom_schemes') ?: config('larasan-mcp.oauth.custom_schemes'),
            ]);
        }

        // A client that asks for no scope still gets a token the MCP endpoint accepts.
        if (Passport::defaultScopes() === []) {
            Passport::defaultScopes([Registrar::OAUTH_SCOPE]);
        }

        $view = config('larasan-mcp.oauth.authorization_view');

        if (is_string($view) && $view !== '') {
            Passport::authorizationView($view);
        }

        if (! $this->app->routesAreCached()) {
            $this->registerRoutes();
        }
    }

    private function registerRoutes(): void
    {
        Route::middleware('throttle:larasan-mcp-oauth')->group(fn () => Mcp::oauthRoutes());

        Mcp::web((string) config('larasan-mcp.http.path'), LarasanServer::class)
            ->middleware((array) config('larasan-mcp.http.middleware'))
            ->name('larasan-mcp');
    }
}
