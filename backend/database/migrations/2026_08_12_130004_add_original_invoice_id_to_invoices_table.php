<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A cancellation invoice references the invoice it cancels via
     * original_invoice_id. Deleting the original invoice sets this column
     * to NULL on the cancellation invoice rather than blocking the delete.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('original_invoice_id')
                ->nullable()
                ->after('invoice_number')
                ->constrained('invoices')
                ->nullOnDelete();

            $table->index('original_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['original_invoice_id']);
            $table->dropIndex(['original_invoice_id']);
            $table->dropColumn('original_invoice_id');
        });
    }
};
