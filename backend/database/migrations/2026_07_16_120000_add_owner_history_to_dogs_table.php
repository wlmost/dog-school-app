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
        Schema::table('dogs', function (Blueprint $table) {
            $table->date('owner_since')->nullable();
            $table->string('age_at_acquisition', 255)->nullable();
            $table->enum('origin', ['breeder', 'shelter', 'private', 'unknown'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dogs', function (Blueprint $table) {
            $table->dropColumn(['owner_since', 'age_at_acquisition', 'origin']);
        });
    }
};
