<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a nullable FK linking each dunning record to the standalone fee
     * invoice document created for it (see design.md Decision D2/D5).
     * `nullOnDelete()` mirrors the existing `original_invoice_id` FK on
     * `invoices` (see 2026_08_12_130004_add_original_invoice_id_to_invoices_table.php):
     * deleting the fee invoice does not cascade-delete the dunning history,
     * it just detaches the link. Rein additiv, kein Backfill nötig — die
     * Tabelle enthält bislang ausschließlich Test-Fixtures, kein
     * Endpunkt legt bislang `InvoiceDunning`-Datensätze an (siehe
     * design.md Migrationen-Abschnitt).
     */
    public function up(): void
    {
        Schema::table('invoice_dunnings', function (Blueprint $table) {
            $table->foreignId('fee_invoice_id')
                ->nullable()
                ->after('fee_amount')
                ->constrained('invoices')
                ->nullOnDelete();

            $table->index('fee_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_dunnings', function (Blueprint $table) {
            $table->dropForeign(['fee_invoice_id']);
            $table->dropIndex(['fee_invoice_id']);
            $table->dropColumn('fee_invoice_id');
        });
    }
};
