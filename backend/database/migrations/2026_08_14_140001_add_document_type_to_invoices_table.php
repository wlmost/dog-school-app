<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a nullable `document_type` discriminator to `invoices`
     * (`null` = regular invoice, `'cancellation'`, `'dunning_fee'`) so that
     * `Invoice::cancellationInvoice()` can no longer be fooled into
     * returning a dunning-fee child document instead of the real
     * cancellation invoice — see design.md Decision D1. Applicationseitig
     * validiert, kein DB-Enum, daher rein additiv und ohne
     * treiberspezifischen Pfad auf MySQL/PostgreSQL/SQLite (CLAUDE.md 4.2).
     *
     * The one-time backfill below rewrites existing cancellation invoices
     * (created exclusively by `InvoiceController::cancel()`, the only
     * writer of `original_invoice_id` at the time this migration was
     * authored — verified per grep, see verification.md) so that
     * `cancellationInvoice()` keeps returning exactly the same records
     * after this migration as before.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('document_type')->nullable()->after('original_invoice_id');

            $table->index('document_type');
        });

        DB::table('invoices')
            ->whereNotNull('original_invoice_id')
            ->update(['document_type' => 'cancellation']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['document_type']);
            $table->dropColumn('document_type');
        });
    }
};
