<?php

declare(strict_types=1);

use App\Mail\BookingConfirmation;
use App\Mail\InvoiceCreated;
use App\Mail\PaymentReminder;
use App\Models\Booking;
use App\Models\Course;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Invoice;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customer = User::factory()->customer()->create();
    
    $this->customerModel = Customer::factory()->for($this->customer)->create();
    $this->dog = Dog::factory()->for($this->customerModel, 'customer')->create();
    
    $this->course = Course::factory()->create();
    $this->session = TrainingSession::factory()
        ->for($this->course)
        ->for($this->trainer, 'trainer')
        ->create();
});

describe('Booking Confirmation Emails', function () {
    it('sends confirmation email when creating a booking', function () {
        $this->actingAs($this->customer);

        $this->postJson('/api/v1/bookings', [
            'trainingSessionId' => $this->session->id,
            'customerId' => $this->customerModel->id,
            'dogId' => $this->dog->id,
            'status' => 'confirmed',
            'bookingDate' => now()->toDateString(),
        ]);

        Mail::assertQueued(BookingConfirmation::class, function ($mail) {
            return $mail->hasTo($this->customer->email);
        });
    });

    it('sends confirmation email when confirming a pending booking', function () {
        $booking = Booking::factory()
            ->for($this->session, 'trainingSession')
            ->for($this->customerModel, 'customer')
            ->for($this->dog)
            ->create(['status' => 'pending']);

        $this->actingAs($this->admin);

        $this->postJson("/api/v1/bookings/{$booking->id}/confirm");

        Mail::assertQueued(BookingConfirmation::class, function ($mail) {
            return $mail->hasTo($this->customer->email);
        });
    });

    it('does not send email when booking creation fails', function () {
        $this->actingAs($this->customer);

        // Try to create booking with invalid data
        $this->postJson('/api/v1/bookings', [
            'trainingSessionId' => 999999, // Non-existent session
            'customerId' => $this->customerModel->id,
            'dogId' => $this->dog->id,
        ])->assertStatus(422);

        Mail::assertNothingQueued();
    });

    it('includes correct booking details in email', function () {
        $this->actingAs($this->customer);

        $this->postJson('/api/v1/bookings', [
            'trainingSessionId' => $this->session->id,
            'customerId' => $this->customerModel->id,
            'dogId' => $this->dog->id,
            'status' => 'confirmed',
            'bookingDate' => now()->toDateString(),
        ]);

        Mail::assertQueued(BookingConfirmation::class, function ($mail) {
            $booking = Booking::latest()->first();
            expect($mail->booking->id)->toBe($booking->id);
            expect($mail->booking->dog->name)->toBe($this->dog->name);
            expect($mail->booking->trainingSession->id)->toBe($this->session->id);
            return true;
        });
    });
});

describe('Invoice Creation Emails', function () {
    it('sends email when creating an invoice', function () {
        $this->actingAs($this->trainer);

        $response = $this->postJson('/api/v1/invoices', [
            'customerId' => $this->customerModel->id,
            'issueDate' => now()->toDateString(),
            'dueDate' => now()->addDays(14)->toDateString(),
            'status' => 'draft',
            'items' => [
                [
                    'description' => 'Welpentraining',
                    'quantity' => 1,
                    'unitPrice' => 100.00,
                    'taxRate' => 19,
                ],
            ],
        ]);

        $response->assertCreated();

        Mail::assertQueued(InvoiceCreated::class, function ($mail) {
            return $mail->hasTo($this->customer->email);
        });
    });

    it('includes correct invoice details in email', function () {
        $this->actingAs($this->trainer);

        $this->postJson('/api/v1/invoices', [
            'customerId' => $this->customerModel->id,
            'issueDate' => now()->toDateString(),
            'dueDate' => now()->addDays(14)->toDateString(),
            'status' => 'draft',
            'items' => [
                [
                    'description' => 'Welpentraining',
                    'quantity' => 1,
                    'unitPrice' => 100.00,
                    'taxRate' => 19,
                ],
            ],
        ]);

        Mail::assertQueued(InvoiceCreated::class, function ($mail) {
            $invoice = Invoice::latest()->first();
            expect($mail->invoice->id)->toBe($invoice->id);
            return true;
        });
    });

    it('does not send email when invoice creation fails', function () {
        $this->actingAs($this->admin);

        $this->postJson('/api/v1/invoices', [
            'customerId' => 999999, // Non-existent customer
            'issueDate' => now()->toDateString(),
            'dueDate' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'description' => 'Test',
                    'quantity' => 1,
                    'unitPrice' => 100.00,
                    'taxRate' => 19,
                ],
            ],
        ])->assertStatus(422);

        Mail::assertNothingQueued();
    });
});

describe('Payment Reminder Emails', function () {
    it('sends reminders for overdue invoices via command', function () {
        // Clear any existing invoices from previous tests
        Invoice::query()->delete();
        
        // Create overdue invoice
        $invoice = Invoice::factory()
            ->for($this->customerModel, 'customer')
            ->create([
                'issue_date' => now(),
                'due_date' => now()->subDays(10),
                'status' => 'sent',
            ]);

        $this->artisan('invoices:send-reminders', ['--days' => 7])
            ->assertSuccessful();

        Mail::assertQueued(PaymentReminder::class, function ($mail) use ($invoice) {
            expect($mail->invoice->id)->toBe($invoice->id);
            return $mail->hasTo($this->customer->email);
        });
    });

    it('does not send reminders for paid invoices', function () {
        // Clear any existing invoices from previous tests
        Invoice::query()->delete();
        
        Invoice::factory()
            ->for($this->customerModel, 'customer')
            ->create([
                'issue_date' => now(),
                'due_date' => now()->subDays(10),
                'status' => 'paid',
                'paid_date' => now()->subDays(2),
            ]);

        $this->artisan('invoices:send-reminders', ['--days' => 7])
            ->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('does not send reminders for cancelled invoices', function () {
        // Clear any existing invoices from previous tests
        Invoice::query()->delete();
        
        Invoice::factory()
            ->for($this->customerModel, 'customer')
            ->create([
                'issue_date' => now(),
                'due_date' => now()->subDays(10),
                'status' => 'cancelled',
            ]);

        $this->artisan('invoices:send-reminders', ['--days' => 7])
            ->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('respects the days overdue threshold', function () {
        // Clear any existing invoices from previous tests
        Invoice::query()->delete();
        
        // Invoice 5 days overdue (below threshold of 7 days)
        Invoice::factory()
            ->for($this->customerModel, 'customer')
            ->create([
                'issue_date' => now(),
                'due_date' => now()->subDays(5),
                'status' => 'sent',
            ]);

        $this->artisan('invoices:send-reminders', ['--days' => 7])
            ->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('sends multiple reminders for multiple overdue invoices', function () {
        // Clear any existing invoices from previous tests
        Invoice::query()->delete();
        
        $user2 = User::factory()->customer()->create();
        $customer2 = Customer::factory()->for($user2)->create();
        
        Invoice::factory()
            ->for($this->customerModel, 'customer')
            ->create([
                'issue_date' => now(),
                'due_date' => now()->subDays(10),
                'status' => 'sent',
            ]);

        Invoice::factory()
            ->for($customer2, 'customer')
            ->create([
                'issue_date' => now(),
                'due_date' => now()->subDays(15),
                'status' => 'sent',
            ]);

        $this->artisan('invoices:send-reminders', ['--days' => 7])
            ->assertSuccessful();

        Mail::assertQueued(PaymentReminder::class, 2);
    });

    it('supports dry run mode without sending emails', function () {
        Invoice::factory()
            ->for($this->customerModel, 'customer')
            ->create([
                'issue_date' => now(),
                'due_date' => now()->subDays(10),
            ]);

        $this->artisan('invoices:send-reminders', ['--days' => 7, '--dry-run' => true])
            ->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('includes invoice details in reminder email', function () {
        // Clear any existing invoices from previous tests
        Invoice::query()->delete();
        
        $invoice = Invoice::factory()
            ->for($this->customerModel, 'customer')
            ->create([
                'invoice_number' => 'INV-2024-999',
                'issue_date' => now(),
                'due_date' => now()->subDays(10),
                'total_amount' => 250.00,
                'status' => 'sent',
            ]);

        $this->artisan('invoices:send-reminders', ['--days' => 7])
            ->assertSuccessful();

        Mail::assertQueued(PaymentReminder::class, function ($mail) use ($invoice) {
            expect($mail->invoice->id)->toBe($invoice->id);
            expect($mail->invoice->invoice_number)->toBe('INV-2024-999');
            expect((float)$mail->invoice->total_amount)->toBe(250.00);
            return true;
        });
    });
});

describe('Email Queue Configuration', function () {
    it('queues booking confirmation email instead of sending immediately', function () {
        $this->actingAs($this->customer);

        $this->postJson('/api/v1/bookings', [
            'trainingSessionId' => $this->session->id,
            'customerId' => $this->customerModel->id,
            'dogId' => $this->dog->id,
            'status' => 'confirmed',
            'bookingDate' => now()->toDateString(),
        ]);

        Mail::assertQueued(BookingConfirmation::class);
        Mail::assertNotSent(BookingConfirmation::class);
    });

    it('queues invoice email instead of sending immediately', function () {
        $this->actingAs($this->trainer);

        $this->postJson('/api/v1/invoices', [
            'customerId' => $this->customerModel->id,
            'issueDate' => now()->toDateString(),
            'dueDate' => now()->addDays(14)->toDateString(),
            'status' => 'draft',
            'items' => [
                [
                    'description' => 'Welpentraining',
                    'quantity' => 1,
                    'unitPrice' => 100.00,
                    'taxRate' => 19,
                ],
            ],
        ]);

        Mail::assertQueued(InvoiceCreated::class);
        Mail::assertNotSent(InvoiceCreated::class);
    });
});
