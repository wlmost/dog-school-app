<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable course_run_id FK to training_sessions.
 *
 * When a session belongs to a CourseRun the FK is set; standalone sessions
 * keep the column NULL. Deleting a CourseRun sets the FK to NULL (no cascade
 * delete of sessions themselves).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->foreignId('course_run_id')
                ->nullable()
                ->after('course_id')
                ->constrained('course_runs')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropForeign(['course_run_id']);
            $table->dropColumn('course_run_id');
        });
    }
};
