<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\CustomerCredit;
use App\Models\Dog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dog scope puppies filters dogs under one year', function () {
    Dog::factory()->create(['date_of_birth' => now()->subMonths(6)]);
    Dog::factory()->create(['date_of_birth' => now()->subYears(2)]);

    $puppies = Dog::puppies()->get();

    expect($puppies)->toHaveCount(1);
});

test('course scope active filters active courses', function () {
    Course::factory()->create(['status' => 'active']);
    Course::factory()->create(['status' => 'completed']);
    Course::factory()->create(['status' => 'cancelled']);

    $activeCourses = Course::active()->get();

    expect($activeCourses)->toHaveCount(1);
});

test('course scope of type filters by course type', function () {
    Course::factory()->create(['course_type' => 'group']);
    Course::factory()->create(['course_type' => 'individual']);
    Course::factory()->create(['course_type' => 'workshop']);

    $groupCourses = Course::ofType('group')->get();

    expect($groupCourses)->toHaveCount(1);
    expect($groupCourses->first()->course_type)->toBe('group');
});

test('training session scope upcoming filters future sessions', function () {
    TrainingSession::factory()->create(['session_date' => now()->addDays(5)]);
    TrainingSession::factory()->create(['session_date' => now()->subDays(5)]);

    $upcoming = TrainingSession::upcoming()->get();

    expect($upcoming)->toHaveCount(1);
});

test('training session scope past filters past sessions', function () {
    TrainingSession::factory()->create(['session_date' => now()->addDays(5)]);
    TrainingSession::factory()->create(['session_date' => now()->subDays(5)]);

    $past = TrainingSession::past()->get();

    expect($past)->toHaveCount(1);
});

test('booking scope confirmed filters confirmed bookings', function () {
    Booking::factory()->create(['status' => 'confirmed']);
    Booking::factory()->create(['status' => 'cancelled']);

    $confirmed = Booking::confirmed()->get();

    expect($confirmed)->toHaveCount(1);
});

test('booking scope attended filters attended bookings', function () {
    Booking::factory()->create(['status' => 'confirmed', 'attended' => true]);
    Booking::factory()->create(['status' => 'confirmed', 'attended' => false]);

    $attended = Booking::attended()->get();

    expect($attended)->toHaveCount(1);
});

test('customer credit scope active filters active credits', function () {
    CustomerCredit::factory()->create(['status' => 'active']);
    CustomerCredit::factory()->create(['status' => 'used']);
    CustomerCredit::factory()->create(['status' => 'expired']);

    $active = CustomerCredit::active()->get();

    expect($active)->toHaveCount(1);
});

test('customer credit scope expired filters expired credits', function () {
    CustomerCredit::factory()->create([
        'status' => 'active',
        'expiration_date' => now()->addDays(10),
    ]);
    CustomerCredit::factory()->create([
        'status' => 'active',
        'expiration_date' => now()->subDays(10),
    ]);

    $expired = CustomerCredit::expired()->get();

    expect($expired)->toHaveCount(1);
});

test('invoice scope unpaid filters unpaid invoices', function () {
    Invoice::factory()->create(['status' => 'paid']);
    Invoice::factory()->create(['status' => 'draft']);
    Invoice::factory()->create(['status' => 'overdue']);

    $unpaid = Invoice::unpaid()->get();

    expect($unpaid)->toHaveCount(2);
});

test('invoice scope overdue filters overdue invoices', function () {
    Invoice::factory()->create([
        'status' => 'draft',
        'due_date' => now()->addDays(5),
    ]);
    Invoice::factory()->create([
        'status' => 'sent',
        'due_date' => now()->subDays(5),
    ]);

    $overdue = Invoice::overdue()->get();

    expect($overdue)->toHaveCount(1);
});

test('payment scope completed filters completed payments', function () {
    Payment::factory()->create(['status' => 'completed']);
    Payment::factory()->create(['status' => 'pending']);
    Payment::factory()->create(['status' => 'failed']);

    $completed = Payment::completed()->get();

    expect($completed)->toHaveCount(1);
});

test('vaccination scope overdue filters overdue vaccinations', function () {
    Vaccination::factory()->create(['next_due_date' => now()->subDays(10)]);
    Vaccination::factory()->create(['next_due_date' => now()->addDays(10)]);

    $overdue = Vaccination::overdue()->get();

    expect($overdue)->toHaveCount(1);
});

test('vaccination scope due soon filters vaccinations due within 30 days', function () {
    Vaccination::factory()->create(['next_due_date' => now()->addDays(15)]);
    Vaccination::factory()->create(['next_due_date' => now()->addDays(45)]);

    $dueSoon = Vaccination::dueSoon()->get();

    expect($dueSoon)->toHaveCount(1);
});
