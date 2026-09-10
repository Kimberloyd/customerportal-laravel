<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->configureRateLimiting();

        // TLS is terminated at Cloudflare's edge, not by this app's own
        // nginx (which only ever speaks plain HTTP internally) -- without
        // this, Laravel/Vite generate absolute asset URLs as http://,
        // which the browser then blocks as mixed content on the https
        // page. Forced unconditionally in production because the public
        // domain is the only production entry point; there is no
        // production traffic this would incorrectly affect.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    private function configureRateLimiting(): void
    {
        $key = static fn (Request $request): string => $request->user()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'ip:'.$request->ip();

        RateLimiter::for('order-writes', fn (Request $request) => Limit::perMinute(30)->by($key($request)));
        RateLimiter::for('return-actions', fn (Request $request) => Limit::perMinute(15)->by($key($request)));
        RateLimiter::for('message-writes', fn (Request $request) => Limit::perMinute(30)->by($key($request)));
        RateLimiter::for('admin-sensitive', fn (Request $request) => Limit::perMinute(10)->by($key($request)));
        RateLimiter::for('report-exports', fn (Request $request) => Limit::perMinute(5)->by($key($request)));
        RateLimiter::for('private-downloads', fn (Request $request) => Limit::perMinute(60)->by($key($request)));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by('ip:'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(6)->by(
            'user:'.($request->user()?->getAuthIdentifier() ?? $request->session()->get('two_factor_login.user_id', 'guest')).'|ip:'.$request->ip()
        ));
    }
}
