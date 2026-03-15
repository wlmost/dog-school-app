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
        Schema::create('anamnesis_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('anamnesis_responses')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('anamnesis_questions')->onDelete('cascade');
            $table->text('answer_value')->nullable();
            $table->timestamps();

            $table->index('response_id');
            $table->index('question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anamnesis_answers');
    }
};
