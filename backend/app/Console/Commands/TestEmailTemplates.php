<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\BookingConfirmationMail;
use App\Mail\InvoiceCreatedMail;
use App\Mail\WelcomeMail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Test Email Templates Command
 *
 * Send test emails to verify template configuration.
 */
class TestEmailTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-templates {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test emails for all templates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $this->info("Sending test emails to: {$email}");
        $this->newLine();

        // Test Booking Confirmation
        $this->info('Sending booking confirmation test...');
        $booking = $this->createMockBooking();
        Mail::to($email)->send(new BookingConfirmationMail($booking));
        $this->info('✓ Booking confirmation sent');
        $this->newLine();

        // Test Invoice
        $this->info('Sending invoice test...');
        $invoice = $this->createMockInvoice();
        Mail::to($email)->send(new InvoiceCreatedMail($invoice));
        $this->info('✓ Invoice sent');
        $this->newLine();

        // Test Welcome
        $this->info('Sending welcome email test...');
        $user = $this->createMockUser();
        Mail::to($email)->send(new WelcomeMail($user, 'TestPassword123!'));
        $this->info('✓ Welcome email sent');
        $this->newLine();

        $this->info('All test emails sent successfully!');

        return Command::SUCCESS;
    }

    /**
     * Create a mock booking for testing.
     */
    private function createMockBooking(): Booking
    {
        $booking = new Booking();
        $booking->id = 1;
        $booking->booking_number = 'TEST-' . date('Ymd') . '-001';
        $booking->booking_date = now();
        $booking->status = 'confirmed';
        
        // Mock relationships
        $booking->setRelation('customer', (object)[
            'user' => (object)[
                'full_name' => 'Max Mustermann',
                'email' => 'max@example.com'
            ]
        ]);

        $booking->setRelation('trainingSession', (object)[
            'session_date' => now()->addDays(7),
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'course' => (object)[
                'name' => 'Welpentraining Gruppe A',
                'description' => 'Grundlagen für Welpen'
            ],
            'trainer' => (object)[
                'full_name' => 'Sarah Schmidt'
            ]
        ]);

        return $booking;
    }

    /**
     * Create a mock invoice for testing.
     */
    private function createMockInvoice(): Invoice
    {
        $invoice = new Invoice();
        $invoice->id = 1;
        $invoice->invoice_number = 'R-' . date('Y') . '-001';
        $invoice->invoice_date = now();
        $invoice->due_date = now()->addDays(14);
        $invoice->total_amount = 89.00;
        $invoice->paid_amount = 0;
        $invoice->status = 'open';

        // Mock relationships
        $invoice->setRelation('customer', (object)[
            'user' => (object)[
                'full_name' => 'Max Mustermann',
                'email' => 'max@example.com'
            ]
        ]);

        return $invoice;
    }

    /**
     * Create a mock user for testing.
     */
    private function createMockUser(): User
    {
        $user = new User();
        $user->id = 999;
        $user->email = 'test@example.com';
        $user->first_name = 'Max';
        $user->last_name = 'Mustermann';
        $user->role = 'customer';

        return $user;
    }
}
