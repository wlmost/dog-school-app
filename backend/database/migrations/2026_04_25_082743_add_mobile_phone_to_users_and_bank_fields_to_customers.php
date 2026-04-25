<?php

declare(strict_types=1);

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_phone', 20)->nullable()->after('phone');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('notes');
            $table->string('bank_account_holder')->nullable()->after('payment_method');
            $table->string('bank_iban', 34)->nullable()->after('bank_account_holder');
            $table->string('bank_bic', 11)->nullable()->after('bank_iban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile_phone');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'bank_account_holder', 'bank_iban', 'bank_bic']);
        });
    }
};
