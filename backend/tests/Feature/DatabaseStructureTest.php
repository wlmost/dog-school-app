<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('users table has correct structure', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasColumns('users', [
        'id', 'email', 'role', 'first_name', 'last_name', 'phone', 
        'password', 'email_verified_at', 'remember_token', 
        'created_at', 'updated_at', 'deleted_at'
    ]))->toBeTrue();
});

test('customers table exists with required columns', function () {
    expect(Schema::hasTable('customers'))->toBeTrue();
    expect(Schema::hasColumns('customers', [
        'id', 'user_id', 'address_line1', 'address_line2', 
        'postal_code', 'city', 'country', 'emergency_contact', 'notes'
    ]))->toBeTrue();
});

test('dogs table exists with required columns', function () {
    expect(Schema::hasTable('dogs'))->toBeTrue();
    expect(Schema::hasColumns('dogs', [
        'id', 'customer_id', 'name', 'breed', 'date_of_birth', 
        'gender', 'neutered', 'weight', 'chip_number', 
        'veterinarian_name', 'veterinarian_contact', 'medical_notes',
        'created_at', 'updated_at', 'deleted_at'
    ]))->toBeTrue();
});

test('vaccinations table exists with required columns', function () {
    expect(Schema::hasTable('vaccinations'))->toBeTrue();
    expect(Schema::hasColumns('vaccinations', [
        'id', 'dog_id', 'vaccination_type', 'vaccination_date', 
        'next_due_date', 'veterinarian', 'document_path'
    ]))->toBeTrue();
});

test('courses table exists with required columns', function () {
    expect(Schema::hasTable('courses'))->toBeTrue();
    expect(Schema::hasColumns('courses', [
        'id', 'trainer_id', 'name', 'description', 'course_type', 
        'max_participants', 'duration_minutes', 'price_per_session', 
        'total_sessions', 'start_date', 'end_date', 'status'
    ]))->toBeTrue();
});

test('training_sessions table exists with required columns', function () {
    expect(Schema::hasTable('training_sessions'))->toBeTrue();
    expect(Schema::hasColumns('training_sessions', [
        'id', 'course_id', 'trainer_id', 'session_date', 
        'start_time', 'end_time', 'location', 'max_participants', 'status', 'notes'
    ]))->toBeTrue();
});

test('bookings table exists with required columns', function () {
    expect(Schema::hasTable('bookings'))->toBeTrue();
    expect(Schema::hasColumns('bookings', [
        'id', 'training_session_id', 'customer_id', 'dog_id', 
        'status', 'booking_date', 'attended', 'notes'
    ]))->toBeTrue();
});

test('credit_packages table exists with required columns', function () {
    expect(Schema::hasTable('credit_packages'))->toBeTrue();
    expect(Schema::hasColumns('credit_packages', [
        'id', 'name', 'total_credits', 'price', 'validity_days', 'description'
    ]))->toBeTrue();
});

test('customer_credits table exists with required columns', function () {
    expect(Schema::hasTable('customer_credits'))->toBeTrue();
    expect(Schema::hasColumns('customer_credits', [
        'id', 'customer_id', 'credit_package_id', 'remaining_credits', 
        'purchase_date', 'expiry_date', 'status'
    ]))->toBeTrue();
});

test('anamnesis_templates table exists with required columns', function () {
    expect(Schema::hasTable('anamnesis_templates'))->toBeTrue();
    expect(Schema::hasColumns('anamnesis_templates', [
        'id', 'trainer_id', 'name', 'description', 'is_default'
    ]))->toBeTrue();
});

test('anamnesis_questions table exists with required columns', function () {
    expect(Schema::hasTable('anamnesis_questions'))->toBeTrue();
    expect(Schema::hasColumns('anamnesis_questions', [
        'id', 'template_id', 'question_text', 'question_type', 
        'options', 'is_required', 'order'
    ]))->toBeTrue();
});

test('anamnesis_responses table exists with required columns', function () {
    expect(Schema::hasTable('anamnesis_responses'))->toBeTrue();
    expect(Schema::hasColumns('anamnesis_responses', [
        'id', 'dog_id', 'template_id', 'completed_at', 'completed_by'
    ]))->toBeTrue();
});

test('anamnesis_answers table exists with required columns', function () {
    expect(Schema::hasTable('anamnesis_answers'))->toBeTrue();
    expect(Schema::hasColumns('anamnesis_answers', [
        'id', 'response_id', 'question_id', 'answer_value'
    ]))->toBeTrue();
});

test('training_logs table exists with required columns', function () {
    expect(Schema::hasTable('training_logs'))->toBeTrue();
    expect(Schema::hasColumns('training_logs', [
        'id', 'dog_id', 'training_session_id', 'trainer_id', 
        'log_date', 'title', 'notes', 'recommendations'
    ]))->toBeTrue();
});

test('training_attachments table exists with required columns', function () {
    expect(Schema::hasTable('training_attachments'))->toBeTrue();
    expect(Schema::hasColumns('training_attachments', [
        'id', 'training_log_id', 'file_type', 'file_path', 
        'file_name', 'uploaded_at'
    ]))->toBeTrue();
});

test('invoices table exists with required columns', function () {
    expect(Schema::hasTable('invoices'))->toBeTrue();
    expect(Schema::hasColumns('invoices', [
        'id', 'customer_id', 'invoice_number', 'invoice_date', 
        'due_date', 'subtotal', 'tax_rate', 'tax_amount', 'total', 
        'status', 'payment_date', 'payment_method', 'notes'
    ]))->toBeTrue();
});

test('invoice_items table exists with required columns', function () {
    expect(Schema::hasTable('invoice_items'))->toBeTrue();
    expect(Schema::hasColumns('invoice_items', [
        'id', 'invoice_id', 'description', 'quantity', 
        'unit_price', 'tax_rate', 'amount'
    ]))->toBeTrue();
});

test('payments table exists with required columns', function () {
    expect(Schema::hasTable('payments'))->toBeTrue();
    expect(Schema::hasColumns('payments', [
        'id', 'invoice_id', 'payment_date', 'amount', 
        'payment_method', 'transaction_id', 'status'
    ]))->toBeTrue();
});
