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
        Schema::table('document_line_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(); // Add nullable foreign key column
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null'); // Handle deletion of related product
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_line_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']); // Drop the foreign key constraint
            $table->dropColumn('product_id'); // Drop the column
        });
    }
};
