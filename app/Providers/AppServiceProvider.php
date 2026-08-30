<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
    }
}
