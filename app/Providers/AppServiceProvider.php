<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Vercel terminates TLS before forwarding the request to PHP.
        // Force generated production URLs (forms, redirects, callbacks)
        // to stay on HTTPS.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
