<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\Team;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerMessagePolicy;
use App\Policies\ProductReturnPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\TeamPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(ProductReturn::class, ProductReturnPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerMessage::class, CustomerMessagePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);

        $this->configureRateLimiting();
        $this->configureSlowQueryLogging();

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

    /**
     * request_id is already in every log line via AssignRequestId's
     * Log::shareContext() call, so a slow query here can be tied straight
     * back to the request that triggered it.
     */
    private function configureSlowQueryLogging(): void
    {
        $thresholdMs = (int) env('SLOW_QUERY_THRESHOLD_MS', 200);

        DB::listen(function (QueryExecuted $query) use ($thresholdMs): void {
            if ($query->time <= $thresholdMs) {
                return;
            }

            Log::warning('Slow query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
            ]);
        });
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
