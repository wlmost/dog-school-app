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
        Schema::create('training_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_log_id')->constrained()->onDelete('cascade');
            $table->enum('file_type', ['image', 'video', 'document']);
            $table->string('file_path');
            $table->string('file_name');
            $table->dateTime('uploaded_at');
            $table->timestamps();

            $table->index('training_log_id');
            $table->index('file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_attachments');
    }
};
