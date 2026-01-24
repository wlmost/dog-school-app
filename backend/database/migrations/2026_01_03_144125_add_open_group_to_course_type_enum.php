<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Drop old check constraint and add new one
            DB::statement("ALTER TABLE courses DROP CONSTRAINT IF EXISTS courses_course_type_check");
            DB::statement("ALTER TABLE courses ADD CONSTRAINT courses_course_type_check CHECK (course_type IN ('group', 'individual', 'workshop', 'open_group'))");
        } elseif ($driver === 'sqlite') {
            // SQLite: Cannot alter constraints directly, would need table recreation
            // Since SQLite doesn't enforce CHECK constraints strictly in older versions,
            // and the original migration already has the column, we can skip this
            // The constraint will be enforced at the application level
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Remove the constraint with 'open_group' and restore original
            DB::statement("ALTER TABLE courses DROP CONSTRAINT IF EXISTS courses_course_type_check");
            DB::statement("ALTER TABLE courses ADD CONSTRAINT courses_course_type_check CHECK (course_type IN ('group', 'individual', 'workshop'))");
        } elseif ($driver === 'sqlite') {
            // SQLite: No action needed
        }
    }
};
