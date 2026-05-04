<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds 'cancellation_requested' to the bookings.status enum so customers
     * can request a cancellation that trainers must approve.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->recreateTableSqlite(
                ['pending', 'confirmed', 'cancelled', 'cancellation_requested']
            );
            return;
        }

        // MySQL / MariaDB
        DB::statement(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('pending','confirmed','cancelled','cancellation_requested')
             NOT NULL DEFAULT 'pending'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Roll back any rows that used the new value before recreating
            DB::table('bookings')
                ->where('status', 'cancellation_requested')
                ->update(['status' => 'pending']);

            $this->recreateTableSqlite(
                ['pending', 'confirmed', 'cancelled']
            );
            return;
        }

        // MySQL / MariaDB: reset rows first
        DB::table('bookings')
            ->where('status', 'cancellation_requested')
            ->update(['status' => 'pending']);

        DB::statement(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('pending','confirmed','cancelled')
             NOT NULL DEFAULT 'pending'"
        );
    }

    /**
     * Recreate the bookings table in SQLite with an updated status CHECK constraint.
     *
     * SQLite does not support ALTER COLUMN, so we create a temporary table,
     * copy the data, drop the original, and rename the temp table.
     *
     * @param list<string> $statusValues
     */
    private function recreateTableSqlite(array $statusValues): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        // 1. Create a new table with the desired schema under a temp name
        Schema::create('bookings_new', function (Blueprint $table) use ($statusValues) {
            $table->id();
            $table->foreignId('training_session_id')
                ->constrained('training_sessions')
                ->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('dog_id')->constrained()->onDelete('cascade');
            $table->enum('status', $statusValues)->default('pending');
            $table->dateTime('booking_date');
            $table->boolean('attended')->default(false);
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Copy existing rows
        DB::statement('INSERT INTO bookings_new SELECT * FROM bookings');

        // 3. Drop the original table (indexes included)
        Schema::drop('bookings');

        // 4. Rename the new table to 'bookings'
        Schema::rename('bookings_new', 'bookings');

        // 5. Re-add the indexes (they were not created on bookings_new)
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('training_session_id');
            $table->index('customer_id');
            $table->index('dog_id');
            $table->index('status');
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
