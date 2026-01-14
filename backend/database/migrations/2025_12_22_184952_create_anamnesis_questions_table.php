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
        Schema::create('anamnesis_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('anamnesis_templates')->onDelete('cascade');
            $table->text('question_text');
            $table->enum('question_type', ['text', 'textarea', 'select', 'multiselect', 'checkbox', 'radio', 'file']);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('template_id');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anamnesis_questions');
    }
};
