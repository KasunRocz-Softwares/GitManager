<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;

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
        date_default_timezone_set('Asia/Colombo');

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like $user->can()
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
    }
}
