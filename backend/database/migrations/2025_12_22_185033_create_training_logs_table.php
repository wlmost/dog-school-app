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
        Schema::create('training_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dog_id')->constrained()->onDelete('cascade');
            $table->foreignId('training_session_id')->nullable()->constrained('training_sessions')->onDelete('set null');
            $table->foreignId('trainer_id')->constrained('users')->onDelete('cascade');
            $table->date('log_date');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();

            $table->index('dog_id');
            $table->index('training_session_id');
            $table->index('trainer_id');
            $table->index('log_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_logs');
    }
};
