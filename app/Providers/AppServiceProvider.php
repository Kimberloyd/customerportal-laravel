<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

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
}
