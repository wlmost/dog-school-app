<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        // Define gates for role-based access control
        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('trainer', function ($user) {
            return $user->isTrainer() || $user->isAdmin();
        });

        Gate::define('customer', function ($user) {
            return $user->isCustomer() || $user->isTrainer() || $user->isAdmin();
        });
    }
}

