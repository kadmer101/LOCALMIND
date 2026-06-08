<?php

declare(strict_types=1);

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
        // When running behind a TLS-terminating proxy (e.g. a hosted preview),
        // force generated asset/URL schemes to https to avoid mixed-content
        // blocking. Disabled by default so local `php artisan serve` over http
        // keeps working unchanged.
        if ((bool) env('APP_FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }
    }
}
