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
        RateLimiter::for('api-projects', function (Request $request) {
            return Limit::perMinute(30)->by('api-projects:'.$request->user()?->id);
        });

        RateLimiter::for('api-scans', function (Request $request) {
            return Limit::perMinute(12)->by('api-scans:'.$request->user()?->id);
        });

        RateLimiter::for('web-projects', function (Request $request) {
            return Limit::perMinute(20)->by('web-projects:'.$request->user()?->id);
        });

        RateLimiter::for('web-scans', function (Request $request) {
            $projectId = $request->route('project')?->id ?? 'unknown';

            return [
                Limit::perMinute(6)->by('web-scans-user:'.$request->user()?->id),
                Limit::perMinute(3)->by('web-scans-project:'.$projectId),
            ];
        });
    }
}
