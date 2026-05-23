<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable course_run_id FK to bookings.
 *
 * When a booking was made as part of a run-booking the FK is set.
 * Deleting a CourseRun sets the FK to NULL so individual booking records
 * are preserved for audit purposes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('course_run_id')
                ->nullable()
                ->after('dog_id')
                ->constrained('course_runs')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['course_run_id']);
            $table->dropColumn('course_run_id');
        });
    }
};
