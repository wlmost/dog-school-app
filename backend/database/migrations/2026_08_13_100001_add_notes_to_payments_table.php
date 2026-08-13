<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an optional free-text reference/notes field to payments. Rein
     * additive Standard-Spalte (nullable text), kein Enum, kein Raw SQL,
     * keine treiberspezifische Semantik — unkritisch für
     * MySQL/PostgreSQL/SQLite (siehe CLAUDE.md 4.2), daher kein
     * DB::connection()->getDriverName()-Switch nötig.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
