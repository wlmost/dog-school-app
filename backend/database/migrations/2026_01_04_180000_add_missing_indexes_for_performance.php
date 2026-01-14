<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add index to customers.trainer_id for faster trainer-customer queries
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->indexExists('customers', 'customers_trainer_id_index')) {
                $table->index('trainer_id');
            }
        });

        // Add indexes for dogs table (customer_id already exists, only add breed)
        Schema::table('dogs', function (Blueprint $table) {
            if (!$this->indexExists('dogs', 'dogs_breed_index')) {
                $table->index('breed');
            }
        });

        // Add indexes for courses table for status filtering
        Schema::table('courses', function (Blueprint $table) {
            if (!$this->indexExists('courses', 'courses_status_index')) {
                $table->index('status');
            }
        });

        // Add indexes for training_sessions for date and course filtering
        Schema::table('training_sessions', function (Blueprint $table) {
            if (!$this->indexExists('training_sessions', 'training_sessions_session_date_index')) {
                $table->index('session_date');
            }
            if (!$this->indexExists('training_sessions', 'training_sessions_course_id_session_date_index')) {
                $table->index(['course_id', 'session_date']); // Composite index for common query
            }
        });

        // Add indexes for payments table
        Schema::table('payments', function (Blueprint $table) {
            if (!$this->indexExists('payments', 'payments_invoice_id_index')) {
                $table->index('invoice_id');
            }
            if (!$this->indexExists('payments', 'payments_payment_date_index')) {
                $table->index('payment_date');
            }
        });

        // Add indexes for invoice_items
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!$this->indexExists('invoice_items', 'invoice_items_invoice_id_index')) {
                $table->index('invoice_id');
            }
        });

        // Add composite indexes for common queries on invoices
        Schema::table('invoices', function (Blueprint $table) {
            if (!$this->indexExists('invoices', 'invoices_customer_id_status_index')) {
                $table->index(['customer_id', 'status']); // For filtering customer invoices by status
            }
            if (!$this->indexExists('invoices', 'invoices_status_due_date_index')) {
                $table->index(['status', 'due_date']); // For finding overdue invoices
            }
        });

        // Add composite indexes for common queries on bookings
        Schema::table('bookings', function (Blueprint $table) {
            if (!$this->indexExists('bookings', 'bookings_customer_id_status_index')) {
                $table->index(['customer_id', 'status']); // For customer bookings by status
            }
            if (!$this->indexExists('bookings', 'bookings_training_session_id_status_index')) {
                $table->index(['training_session_id', 'status']); // For session bookings
            }
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = \DB::select("
            SELECT indexname 
            FROM pg_indexes 
            WHERE tablename = ? AND indexname = ?
        ", [$table, $index]);
        
        return count($indexes) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if ($this->indexExists('customers', 'customers_trainer_id_index')) {
                $table->dropIndex(['trainer_id']);
            }
        });

        Schema::table('dogs', function (Blueprint $table) {
            if ($this->indexExists('dogs', 'dogs_breed_index')) {
                $table->dropIndex(['breed']);
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            if ($this->indexExists('courses', 'courses_status_index')) {
                $table->dropIndex(['status']);
            }
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            if ($this->indexExists('training_sessions', 'training_sessions_session_date_index')) {
                $table->dropIndex(['session_date']);
            }
            if ($this->indexExists('training_sessions', 'training_sessions_course_id_session_date_index')) {
                $table->dropIndex(['course_id', 'session_date']);
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if ($this->indexExists('payments', 'payments_invoice_id_index')) {
                $table->dropIndex(['invoice_id']);
            }
            if ($this->indexExists('payments', 'payments_payment_date_index')) {
                $table->dropIndex(['payment_date']);
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if ($this->indexExists('invoice_items', 'invoice_items_invoice_id_index')) {
                $table->dropIndex(['invoice_id']);
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if ($this->indexExists('invoices', 'invoices_customer_id_status_index')) {
                $table->dropIndex(['customer_id', 'status']);
            }
            if ($this->indexExists('invoices', 'invoices_status_due_date_index')) {
                $table->dropIndex(['status', 'due_date']);
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if ($this->indexExists('bookings', 'bookings_customer_id_status_index')) {
                $table->dropIndex(['customer_id', 'status']);
            }
            if ($this->indexExists('bookings', 'bookings_training_session_id_status_index')) {
                $table->dropIndex(['training_session_id', 'status']);
            }
        });
    }
};
