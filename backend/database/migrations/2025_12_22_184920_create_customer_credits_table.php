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
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('credit_package_id')->constrained()->onDelete('cascade');
            $table->integer('remaining_credits');
            $table->date('purchase_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['active', 'expired', 'used'])->default('active');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};
