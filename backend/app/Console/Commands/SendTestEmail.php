<?php

namespace App\Console\Commands;

use App\Mail\BookingConfirmation;
use App\Mail\InvoiceCreated;
use App\Mail\PaymentReminder;
use App\Mail\WelcomeEmail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {recipient} {--type=all : Type of email to send (all|welcome|booking|invoice|reminder)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test emails to verify email configuration and templates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $recipient = $this->argument('recipient');
        $type = $this->option('type');

        $this->info("Sending test email(s) to: {$recipient}");
        $this->newLine();

        try {
            match ($type) {
                'welcome' => $this->sendWelcomeEmail($recipient),
                'booking' => $this->sendBookingEmail($recipient),
                'invoice' => $this->sendInvoiceEmail($recipient),
                'reminder' => $this->sendReminderEmail($recipient),
                default => $this->sendAllEmails($recipient),
            };

            $this->newLine();
            $this->info('✓ Test email(s) sent successfully!');
            $this->info('Check Mailpit at http://localhost:8025 or the recipient inbox.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to send test email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function sendAllEmails(string $recipient): void
    {
        $this->sendWelcomeEmail($recipient);
        $this->sendBookingEmail($recipient);
        $this->sendInvoiceEmail($recipient);
        $this->sendReminderEmail($recipient);
    }

    protected function sendWelcomeEmail(string $recipient): void
    {
        $this->line('📧 Sending Welcome Email...');
        
        $testUser = User::factory()->make([
            'email' => $recipient,
            'first_name' => 'Test',
            'last_name' => 'Benutzer',
            'role' => 'customer',
        ]);

        Mail::to($recipient)->send(new WelcomeEmail($testUser, 'TestPassword123!'));
        
        $this->line('   ✓ Welcome email sent');
    }

    protected function sendBookingEmail(string $recipient): void
    {
        $this->line('📧 Sending Booking Confirmation Email...');
        
        // Get or create a real booking for realistic data
        $booking = Booking::with(['trainingSession.course', 'dog', 'customer.user'])
            ->first();

        if (!$booking) {
            $this->warn('   ⚠ No bookings found in database. Skipping booking email.');
            return;
        }

        // Temporarily override customer email for testing
        $originalEmail = $booking->customer->user->email;
        $booking->customer->user->email = $recipient;

        Mail::to($recipient)->send(new BookingConfirmation($booking));

        // Restore original email
        $booking->customer->user->email = $originalEmail;
        
        $this->line('   ✓ Booking confirmation sent');
    }

    protected function sendInvoiceEmail(string $recipient): void
    {
        $this->line('📧 Sending Invoice Created Email...');
        
        // Get or create a real invoice for realistic data
        $invoice = Invoice::with(['customer.user', 'items'])->first();

        if (!$invoice) {
            $this->warn('   ⚠ No invoices found in database. Skipping invoice email.');
            return;
        }

        // Temporarily override customer email for testing
        $originalEmail = $invoice->customer->user->email;
        $invoice->customer->user->email = $recipient;

        Mail::to($recipient)->send(new InvoiceCreated($invoice));

        // Restore original email
        $invoice->customer->user->email = $originalEmail;
        
        $this->line('   ✓ Invoice created email sent');
    }

    protected function sendReminderEmail(string $recipient): void
    {
        $this->line('📧 Sending Payment Reminder Email...');
        
        // Get overdue invoice
        $invoice = Invoice::with(['customer.user', 'items'])
            ->where('status', 'pending')
            ->orWhere('status', 'overdue')
            ->first();

        if (!$invoice) {
            // Create temporary overdue invoice
            $this->warn('   ⚠ No overdue invoices found. Skipping payment reminder.');
            return;
        }

        // Temporarily override customer email for testing
        $originalEmail = $invoice->customer->user->email;
        $invoice->customer->user->email = $recipient;

        Mail::to($recipient)->send(new PaymentReminder($invoice, 7));

        // Restore original email
        $invoice->customer->user->email = $originalEmail;
        
        $this->line('   ✓ Payment reminder sent');
    }
}
