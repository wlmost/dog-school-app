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
        $prefix = Schema::getConnection()->getTablePrefix();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$prefix}courses` MODIFY COLUMN course_type ENUM('group','individual','workshop','open_group') NOT NULL");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Drop old check constraint and add new one
            DB::statement("ALTER TABLE \"{$prefix}courses\" DROP CONSTRAINT IF EXISTS {$prefix}courses_course_type_check");
            DB::statement("ALTER TABLE \"{$prefix}courses\" ADD CONSTRAINT {$prefix}courses_course_type_check CHECK (course_type IN ('group', 'individual', 'workshop', 'open_group'))");
        }
        // SQLite: ENUM is stored as VARCHAR, no constraint change needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $prefix = Schema::getConnection()->getTablePrefix();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$prefix}courses` MODIFY COLUMN course_type ENUM('group','individual','workshop') NOT NULL");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Remove the constraint with 'open_group' and restore original
            DB::statement("ALTER TABLE \"{$prefix}courses\" DROP CONSTRAINT IF EXISTS {$prefix}courses_course_type_check");
            DB::statement("ALTER TABLE \"{$prefix}courses\" ADD CONSTRAINT {$prefix}courses_course_type_check CHECK (course_type IN ('group', 'individual', 'workshop'))");
        }
        // SQLite: no action needed
    }
};
