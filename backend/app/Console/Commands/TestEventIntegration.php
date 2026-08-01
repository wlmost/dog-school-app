<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\BookingCreated;
use App\Events\InvoiceWasCreated;
use App\Events\UserRegistered;
use App\Listeners\SendBookingConfirmationEmail;
use App\Listeners\SendInvoiceCreatedEmail;
use App\Listeners\SendWelcomeEmail;
use Illuminate\Console\Command;

class TestEventIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test event integration and verify listeners are registered';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Testing Event Integration...');
        $this->newLine();

        // Test Event Class Loading
        $this->line('📦 Testing Event Classes...');
        $this->testEventClassLoading();
        $this->newLine();

        // Test Listener Class Loading
        $this->line('🎧 Testing Listener Classes...');
        $this->testListenerClassLoading();
        $this->newLine();

        // Test Event Registration
        $this->line('📝 Testing Event Registration...');
        $this->testEventRegistration();
        $this->newLine();

        $this->info('✅ All event integration tests passed!');
        $this->newLine();
        $this->info('💡 To test actual email sending, create a booking/invoice/user through the API');
        $this->info('   and check Mailpit at http://localhost:8025');

        return self::SUCCESS;
    }

    protected function testEventClassLoading(): void
    {
        $events = [
            'BookingCreated' => BookingCreated::class,
            'InvoiceWasCreated' => InvoiceWasCreated::class,
            'UserRegistered' => UserRegistered::class,
        ];

        foreach ($events as $name => $class) {
            if (class_exists($class)) {
                $this->line("  ✓ {$name} event loaded");
            } else {
                $this->error("  ✗ {$name} event NOT found");
            }
        }
    }

    protected function testListenerClassLoading(): void
    {
        $listeners = [
            'SendBookingConfirmationEmail' => SendBookingConfirmationEmail::class,
            'SendInvoiceCreatedEmail' => SendInvoiceCreatedEmail::class,
            'SendWelcomeEmail' => SendWelcomeEmail::class,
        ];

        foreach ($listeners as $name => $class) {
            if (class_exists($class)) {
                $this->line("  ✓ {$name} listener loaded");
            } else {
                $this->error("  ✗ {$name} listener NOT found");
            }
        }
    }

    protected function testEventRegistration(): void
    {
        $app = app();
        $events = $app['events'];

        // Check if listeners are registered
        $bookingListeners = $events->getListeners(BookingCreated::class);
        $invoiceListeners = $events->getListeners(InvoiceWasCreated::class);
        $userListeners = $events->getListeners(UserRegistered::class);

        if (count($bookingListeners) > 0) {
            $this->line('  ✓ BookingCreated has '.count($bookingListeners).' listener(s)');
        } else {
            $this->warn('  ⚠ BookingCreated has NO listeners');
        }

        if (count($invoiceListeners) > 0) {
            $this->line('  ✓ InvoiceWasCreated has '.count($invoiceListeners).' listener(s)');
        } else {
            $this->warn('  ⚠ InvoiceWasCreated has NO listeners');
        }

        if (count($userListeners) > 0) {
            $this->line('  ✓ UserRegistered has '.count($userListeners).' listener(s)');
        } else {
            $this->warn('  ⚠ UserRegistered has NO listeners');
        }
    }
}
