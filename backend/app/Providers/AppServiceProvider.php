<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\BookingCreated;
use App\Events\UserRegistered;
use App\Listeners\SendBookingConfirmationEmail;
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

        // Configure rate limiting for public contact form (3 submissions per 5 minutes per IP)
        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->ip());
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

        // Register event listeners.
        //
        // InvoiceWasSent/SendInvoiceEmail is deliberately NOT registered
        // here: Laravel's automatic event discovery already registers any
        // Listener::handle() method typed against an Event class (see
        // `php artisan event:list`). Registering it here too caused
        // SendInvoiceEmail to run twice per InvoiceWasSent dispatch,
        // sending the invoice email twice for every click of "Aus der App
        // versenden" once the listener switched to synchronous Mail::send()
        // (see add-invoice-send-flow task-review.test-report.md). The two
        // registrations below are known to have the same double-dispatch
        // issue (BookingCreated/UserRegistered use ShouldQueue, so it is
        // masked by queue deduplication behavior rather than fixed) — left
        // untouched here as out of scope for this change, see the
        // fix-duplicate-event-listener-registration follow-up.
        Event::listen(
            BookingCreated::class,
            SendBookingConfirmationEmail::class
        );

        Event::listen(
            UserRegistered::class,
            SendWelcomeEmail::class
        );
    }
}
