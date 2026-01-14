<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop old check constraint
        DB::statement("ALTER TABLE courses DROP CONSTRAINT IF EXISTS courses_course_type_check");
        
        // Add new check constraint with 'open_group'
        DB::statement("ALTER TABLE courses ADD CONSTRAINT courses_course_type_check CHECK (course_type IN ('group', 'individual', 'workshop', 'open_group'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: PostgreSQL doesn't support removing enum values easily
        // You would need to recreate the enum type
    }
};
