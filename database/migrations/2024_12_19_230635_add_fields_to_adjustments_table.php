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
        Schema::table('adjustments', function (Blueprint $table) {
            $table->string('status')->default('pending'); // e.g., pending, approved, reversed
            $table->string('transaction_id')->nullable(); // For linking to a related transaction
            $table->integer('previous_quantity')->nullable(); // For inventory adjustments
            $table->integer('new_quantity')->nullable(); // For inventory adjustments
            $table->decimal('previous_price', 10, 2)->nullable(); // For price adjustments
            $table->decimal('new_price', 10, 2)->nullable(); // For price adjustments
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'transaction_id',
                'previous_quantity',
                'new_quantity',
                'previous_price',
                'new_price',
            ]);

        });
    }
};
