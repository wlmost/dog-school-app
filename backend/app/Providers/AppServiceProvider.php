<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\BookingCreated;
use App\Events\InvoiceWasCreated;
use App\Events\UserRegistered;
use App\Listeners\SendBookingConfirmationEmail;
use App\Listeners\SendInvoiceCreatedEmail;
use App\Listeners\SendWelcomeEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Configure rate limiting for API routes
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Configure rate limiting for login attempts
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Set password validation rules
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

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

        // Register event listeners
        Event::listen(
            BookingCreated::class,
            SendBookingConfirmationEmail::class
        );

        Event::listen(
            InvoiceWasCreated::class,
            SendInvoiceCreatedEmail::class
        );

        Event::listen(
            UserRegistered::class,
            SendWelcomeEmail::class
        );
    }
}

