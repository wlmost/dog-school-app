<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('course is active when status is active', function () {
    $course = Course::factory()->create(['status' => 'active']);

    expect($course->isActive())->toBeTrue();
});

test('course is not active when status is completed', function () {
    $course = Course::factory()->create(['status' => 'completed']);

    expect($course->isActive())->toBeFalse();
});

test('course is full when max participants reached', function () {
    $course = Course::factory()->create(['max_participants' => 2]);
    $session = TrainingSession::factory()->create([
        'course_id' => $course->id,
        'max_participants' => 2,
        'status' => 'scheduled',
    ]);
    
    Booking::factory()->count(2)->create([
        'training_session_id' => $session->id,
        'status' => 'confirmed',
    ]);

    $course = $course->fresh();
    expect($course->isFull())->toBeTrue();
});

test('training session is full when bookings equal max participants', function () {
    $session = TrainingSession::factory()->create(['max_participants' => 3]);
    
    Booking::factory()->count(3)->create([
        'training_session_id' => $session->id,
        'status' => 'confirmed',
    ]);

    expect($session->isFull())->toBeTrue();
});

test('training session is not full when spots available', function () {
    $session = TrainingSession::factory()->create(['max_participants' => 5]);
    
    Booking::factory()->count(2)->create([
        'training_session_id' => $session->id,
        'status' => 'confirmed',
    ]);

    expect($session->isFull())->toBeFalse();
});

test('training session is past when date is in the past', function () {
    $session = TrainingSession::factory()->create(['session_date' => now()->subDays(5)]);

    expect($session->isPast())->toBeTrue();
});

test('booking is confirmed when status is confirmed', function () {
    $booking = Booking::factory()->create(['status' => 'confirmed']);

    expect($booking->isConfirmed())->toBeTrue();
});

test('booking is cancelled when status is cancelled', function () {
    $booking = Booking::factory()->create(['status' => 'cancelled']);

    expect($booking->isCancelled())->toBeTrue();
});

test('customer credit is active when status is active', function () {
    $credit = CustomerCredit::factory()->create(['status' => 'active']);

    expect($credit->isActive())->toBeTrue();
});

test('customer credit is expired when expiry date is in the past', function () {
    $credit = CustomerCredit::factory()->create([
        'expiry_date' => now()->subDays(5),
    ]);

    expect($credit->isExpired())->toBeTrue();
});

test('customer credit cannot use more credits than remaining', function () {
    $credit = CustomerCredit::factory()->create([
        'remaining_credits' => 3,
        'status' => 'active',
    ]);

    $result = $credit->useCredit(5);

    expect($result)->toBeFalse();
    expect($credit->remaining_credits)->toBe(3);
});

test('invoice is paid when status is paid', function () {
    $invoice = Invoice::factory()->create(['status' => 'paid']);

    expect($invoice->isPaid())->toBeTrue();
});

test('invoice is overdue when due date passed and not paid', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'sent',
        'due_date' => now()->subDays(5),
    ]);

    expect($invoice->isOverdue())->toBeTrue();
});

test('invoice remaining balance is calculated correctly', function () {
    $invoice = Invoice::factory()->create(['total' => 100.00]);
    
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 60.00,
        'status' => 'completed',
    ]);

    expect($invoice->remaining_balance)->toBe(40.00);
});

test('payment is completed when status is completed', function () {
    $payment = Payment::factory()->create(['status' => 'completed']);

    expect($payment->isCompleted())->toBeTrue();
});

test('payment is pending when status is pending', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    expect($payment->isPending())->toBeTrue();
});

test('payment is failed when status is failed', function () {
    $payment = Payment::factory()->create(['status' => 'failed']);

    expect($payment->isFailed())->toBeTrue();
});

test('vaccination is due when next due date is in the past', function () {
    $vaccination = Vaccination::factory()->create([
        'next_due_date' => now()->subDays(5),
    ]);

    expect($vaccination->isDue())->toBeTrue();
});

test('vaccination is not due when next due date is in the future', function () {
    $vaccination = Vaccination::factory()->create([
        'next_due_date' => now()->addDays(30),
    ]);

    expect($vaccination->isDue())->toBeFalse();
});
