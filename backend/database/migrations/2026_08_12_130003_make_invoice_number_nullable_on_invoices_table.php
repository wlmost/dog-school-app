<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Draft invoices no longer receive an invoice number at creation time
     * (numbers are only assigned when an invoice is finalized). The existing
     * unique index on invoice_number remains valid: MySQL, PostgreSQL and
     * SQLite all allow multiple NULL values in a unique index.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable(false)->change();
        });
    }
};
