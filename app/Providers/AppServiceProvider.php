<?php

namespace App\Providers;

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
        if (! $this->app->runningInConsole()) {
            $host = request()->getHost();
            if (
                request()->header('X-Forwarded-Proto') === 'https'
                || str_ends_with($host, '.lhr.life')
                || str_ends_with($host, '.trycloudflare.com')
            ) {
                URL::forceScheme('https');
            }
        }
    }
}
