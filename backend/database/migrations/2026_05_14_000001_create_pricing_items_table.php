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
        Schema::create('pricing_items', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100);
            $table->string('title', 200);
            $table->decimal('price', 8, 2);
            $table->string('unit', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_from_price')->default(false);
            $table->timestamps();

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_items');
    }
};
