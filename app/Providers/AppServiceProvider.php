<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
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
        // Use our hairline-styled pagination partial (resources/views/vendor/pagination/minimal.blade.php)
        // everywhere ->paginate() is rendered, instead of Laravel's default Tailwind view.
        Paginator::defaultView('vendor.pagination.minimal');

        // Every generated URL (assets, redirects, form actions, signed links)
        // stays on https in production, so a single http:// link can't drop a
        // shopper's session cookie onto a plaintext hop.
        URL::forceHttps($this->app->isProduction());

        // A mistyped `migrate:fresh` or `db:wipe` against the live database is
        // unrecoverable. Refuse them outright once APP_ENV=production.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
