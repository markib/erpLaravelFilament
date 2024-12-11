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
        Schema::create('adjustmentables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')->constrained('adjustments')->onDelete('cascade');
            $table->string('adjustmentable_type'); // Polymorphic type column
            $table->unsignedBigInteger('adjustmentable_id'); // Polymorphic ID column
            $table->timestamps();

            $table->index(['adjustmentable_type', 'adjustmentable_id'], 'adjustmentable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustmentables');
    }
};
