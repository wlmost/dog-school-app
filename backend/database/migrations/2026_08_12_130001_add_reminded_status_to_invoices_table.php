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
     * Adds 'reminded' to the invoices.status enum so overdue invoices can be
     * marked as having received a dunning notice.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->recreateTableSqlite(
                ['draft', 'sent', 'paid', 'overdue', 'cancelled', 'reminded']
            );

            return;
        }

        if ($driver === 'pgsql') {
            // PostgreSQL does not have ENUM; the column is VARCHAR with a CHECK constraint.
            // Add the new value to the existing CHECK constraint.
            $prefix = DB::getTablePrefix();
            DB::statement("ALTER TABLE \"{$prefix}invoices\" DROP CONSTRAINT IF EXISTS {$prefix}invoices_status_check");
            DB::statement("ALTER TABLE \"{$prefix}invoices\" ADD CONSTRAINT {$prefix}invoices_status_check CHECK (status IN ('draft','sent','paid','overdue','cancelled','reminded'))");

            return;
        }

        // MySQL / MariaDB
        $prefix = DB::getTablePrefix();
        DB::statement(
            "ALTER TABLE `{$prefix}invoices` MODIFY COLUMN status
             ENUM('draft','sent','paid','overdue','cancelled','reminded')
             NOT NULL DEFAULT 'draft'"
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
            DB::table('invoices')
                ->where('status', 'reminded')
                ->update(['status' => 'sent']);

            $this->recreateTableSqlite(
                ['draft', 'sent', 'paid', 'overdue', 'cancelled']
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::table('invoices')
                ->where('status', 'reminded')
                ->update(['status' => 'sent']);
            $prefix = DB::getTablePrefix();
            DB::statement("ALTER TABLE \"{$prefix}invoices\" DROP CONSTRAINT IF EXISTS {$prefix}invoices_status_check");
            DB::statement("ALTER TABLE \"{$prefix}invoices\" ADD CONSTRAINT {$prefix}invoices_status_check CHECK (status IN ('draft','sent','paid','overdue','cancelled'))");

            return;
        }

        // MySQL / MariaDB: reset rows first
        DB::table('invoices')
            ->where('status', 'reminded')
            ->update(['status' => 'sent']);

        $prefix = DB::getTablePrefix();
        DB::statement(
            "ALTER TABLE `{$prefix}invoices` MODIFY COLUMN status
             ENUM('draft','sent','paid','overdue','cancelled')
             NOT NULL DEFAULT 'draft'"
        );
    }

    /**
     * Recreate the invoices table in SQLite with an updated status CHECK constraint.
     *
     * SQLite does not support ALTER COLUMN, so we create a temporary table,
     * copy the data, drop the original, and rename the temp table.
     *
     * @param  list<string>  $statusValues
     */
    private function recreateTableSqlite(array $statusValues): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        $prefix = DB::getTablePrefix();

        // 1. Create a new table with the desired schema under a temp name
        Schema::create('invoices_new', function (Blueprint $table) use ($statusValues) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->enum('status', $statusValues)->default('draft');
            $table->decimal('total_amount', 10, 2);
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Copy existing rows – use prefixed table names in raw SQL
        DB::statement("INSERT INTO \"{$prefix}invoices_new\" SELECT * FROM \"{$prefix}invoices\"");

        // 3. Drop the original table (indexes included)
        Schema::drop('invoices');

        // 4. Rename the new table to 'invoices'
        Schema::rename('invoices_new', 'invoices');

        // 5. Re-add the indexes (they were not created on invoices_new)
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('customer_id');
            $table->index('invoice_number');
            $table->index('status');
            $table->index('issue_date');
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
