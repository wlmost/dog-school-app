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
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dog_id')->constrained()->onDelete('cascade');
            $table->string('vaccination_type');
            $table->date('vaccination_date');
            $table->date('next_due_date')->nullable();
            $table->string('veterinarian')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();

            $table->index('dog_id');
            $table->index('next_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
    }
};
