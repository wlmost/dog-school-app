<?php

declare(strict_types=1);

use App\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('users table has correct structure', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasColumns('users', [
        'id', 'email', 'role', 'first_name', 'last_name', 'phone',
        'password', 'email_verified_at', 'remember_token',
        'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('customers table exists with required columns', function () {
    expect(Schema::hasTable('customers'))->toBeTrue();
    expect(Schema::hasColumns('customers', [
        'id', 'user_id', 'address_line1', 'address_line2',
        'postal_code', 'city', 'country', 'emergency_contact', 'notes',
    ]))->toBeTrue();
});

test('dogs table exists with required columns', function () {
    expect(Schema::hasTable('dogs'))->toBeTrue();
    expect(Schema::hasColumns('dogs', [
        'id', 'customer_id', 'name', 'breed', 'date_of_birth',
        'gender', 'neutered', 'weight', 'chip_number', 'color',
        'veterinarian', 'special_needs', 'notes', 'is_active',
        'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('vaccinations table exists with required columns', function () {
    expect(Schema::hasTable('vaccinations'))->toBeTrue();
    expect(Schema::hasColumns('vaccinations', [
        'id', 'dog_id', 'vaccination_type', 'vaccination_date',
        'next_due_date', 'veterinarian', 'document_path',
    ]))->toBeTrue();
});

test('courses table exists with required columns', function () {
    expect(Schema::hasTable('courses'))->toBeTrue();
    expect(Schema::hasColumns('courses', [
        'id', 'trainer_id', 'name', 'description', 'course_type',
        'max_participants', 'duration_minutes', 'price_per_session',
        'total_sessions', 'start_date', 'end_date', 'status',
    ]))->toBeTrue();
});

test('training_sessions table exists with required columns', function () {
    expect(Schema::hasTable('training_sessions'))->toBeTrue();
    expect(Schema::hasColumns('training_sessions', [
        'id', 'course_id', 'trainer_id', 'session_date',
        'start_time', 'end_time', 'location', 'max_participants', 'status', 'notes',
    ]))->toBeTrue();
});

test('bookings table exists with required columns', function () {
    expect(Schema::hasTable('bookings'))->toBeTrue();
    expect(Schema::hasColumns('bookings', [
        'id', 'training_session_id', 'customer_id', 'dog_id',
        'status', 'booking_date', 'attended', 'notes',
    ]))->toBeTrue();
});

test('credit_packages table exists with required columns', function () {
    expect(Schema::hasTable('credit_packages'))->toBeTrue();
    expect(Schema::hasColumns('credit_packages', [
        'id', 'name', 'total_credits', 'price', 'validity_days', 'description',
    ]))->toBeTrue();
});

test('customer_credits table exists with required columns', function () {
    expect(Schema::hasTable('customer_credits'))->toBeTrue();
    expect(Schema::hasColumns('customer_credits', [
        'id', 'customer_id', 'credit_package_id', 'total_credits', 'remaining_credits',
        'purchase_date', 'expiration_date', 'status',
    ]))->toBeTrue();
});

test('anamnesis_templates table exists with required columns', function () {
    expect(Schema::hasTable('anamnesis_templates'))->toBeTrue();
    expect(Schema::hasColumns('anamnesis_templates', [
        'id', 'trainer_id', 'name', 'description', 'is_default',
    ]))->toBeTrue();
});

test('anamnesis_questions table exists with required columns', function () {
    expect(Schema::hasTable('anamnesis_questions'))->toBeTrue();
    expect(Schema::hasColumns('anamnesis_questions', [
        'id', 'template_id', 'question_text', 'question_type',
        'options', 'is_required', 'order',
    ]))->toBeTrue();
});

test('anamnesis_responses table exists with required columns', function () {
    expect(Schema::hasTable('anamnesis_responses'))->toBeTrue();
    expect(Schema::hasColumns('anamnesis_responses', [
        'id', 'dog_id', 'template_id', 'completed_at', 'completed_by',
    ]))->toBeTrue();
});

test('anamnesis_answers table exists with required columns', function () {
    expect(Schema::hasTable('anamnesis_answers'))->toBeTrue();
    expect(Schema::hasColumns('anamnesis_answers', [
        'id', 'response_id', 'question_id', 'answer_value',
    ]))->toBeTrue();
});

test('training_logs table exists with required columns', function () {
    expect(Schema::hasTable('training_logs'))->toBeTrue();
    expect(Schema::hasColumns('training_logs', [
        'id', 'dog_id', 'training_session_id', 'trainer_id',
        'progress_notes', 'behavior_notes', 'homework',
    ]))->toBeTrue();
});

test('training_attachments table exists with required columns', function () {
    expect(Schema::hasTable('training_attachments'))->toBeTrue();
    expect(Schema::hasColumns('training_attachments', [
        'id', 'training_log_id', 'file_type', 'file_path',
        'file_name', 'uploaded_at',
    ]))->toBeTrue();
});

test('invoices table exists with required columns', function () {
    expect(Schema::hasTable('invoices'))->toBeTrue();
    expect(Schema::hasColumns('invoices', [
        'id', 'customer_id', 'invoice_number', 'original_invoice_id', 'status', 'total_amount',
        'issue_date', 'due_date', 'paid_date', 'notes',
    ]))->toBeTrue();
});

// add-invoice-dunning-dashboard T01 (M1): document_type-Diskriminator
// zwischen regulärer Rechnung, Stornorechnung und Mahngebühren-Dokument
// (siehe design.md Decision D1).
test('invoices table has document_type column', function () {
    expect(Schema::hasColumns('invoices', ['document_type']))->toBeTrue();
});

// add-invoice-dunning-dashboard T01 (M1): Backfill bestehender
// Stornorechnungen auf document_type = 'cancellation' beim Migrieren.
// Simuliert den Produktivfall: eine Stornorechnung existiert bereits
// (original_invoice_id gesetzt), bevor die document_type-Spalte
// hinzugefügt wird — anschließend muss die Migration sie korrekt
// zurückschreiben.
test('document_type backfill sets cancellation on pre-existing cancellation invoices', function () {
    $migrationPath = 'database/migrations/2026_08_14_140001_add_document_type_to_invoices_table.php';

    Artisan::call('migrate:rollback', ['--path' => $migrationPath, '--force' => true]);

    $original = Invoice::factory()->create(['status' => 'sent']);

    DB::table('invoices')->insert([
        'customer_id' => $original->customer_id,
        'invoice_number' => 'INV-STORNO-'.$original->id,
        'original_invoice_id' => $original->id,
        'status' => 'sent',
        'total_amount' => $original->total_amount,
        'issue_date' => now(),
        'due_date' => now()->addDays(14),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('migrate', ['--path' => $migrationPath, '--force' => true]);

    $cancellation = Invoice::where('original_invoice_id', $original->id)->first();

    expect($cancellation->document_type)->toBe('cancellation');
});

// add-invoice-status-lifecycle: invoice_number ist seit Migration M3 nullable
// (Entwürfe erhalten erst bei finalize()/cancel() eine Nummer, siehe
// design.md Decision D2/D5) — dieser Test dokumentiert den Schema-Vertrag
// zusätzlich zur reinen Spalten-Existenzprüfung oben.
test('invoice_number column on invoices table is nullable', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft', 'invoice_number' => null]);

    expect($invoice->refresh()->invoice_number)->toBeNull();
});

// add-invoice-status-lifecycle T01 (M2): Mahnstufen-Datenmodell.
// add-invoice-dunning-dashboard T01 (M2): fee_invoice_id verknüpft jeden
// Mahn-Datensatz mit seinem eigenständigen Gebührendokument.
test('invoice_dunnings table exists with required columns', function () {
    expect(Schema::hasTable('invoice_dunnings'))->toBeTrue();
    expect(Schema::hasColumns('invoice_dunnings', [
        'id', 'invoice_id', 'level', 'dunning_date', 'fee_amount', 'fee_invoice_id',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('invoice_items table exists with required columns', function () {
    expect(Schema::hasTable('invoice_items'))->toBeTrue();
    expect(Schema::hasColumns('invoice_items', [
        'id', 'invoice_id', 'description', 'quantity',
        'unit_price', 'tax_rate', 'amount',
    ]))->toBeTrue();
});

test('payments table exists with required columns', function () {
    expect(Schema::hasTable('payments'))->toBeTrue();
    expect(Schema::hasColumns('payments', [
        'id', 'invoice_id', 'payment_date', 'amount',
        'payment_method', 'transaction_id', 'status',
    ]))->toBeTrue();
});

test('dog_registration_requests table exists with required columns', function () {
    expect(Schema::hasTable('dog_registration_requests'))->toBeTrue();
    expect(Schema::hasColumns('dog_registration_requests', [
        'id', 'customer_id', 'name', 'breed', 'gender',
        'date_of_birth', 'neutered', 'chip_number', 'notes',
        'status', 'reviewed_by', 'reviewed_at',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});
