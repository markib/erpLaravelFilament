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
        Schema::table('bills', function (Blueprint $table) {
            $table->string('discount_method')->default('per_line_item')->after('currency_code');
            $table->string('discount_computation')->default('percentage')->after('discount_method');
            $table->integer('discount_rate')->default(0)->after('discount_computation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['discount_method', 'discount_computation', 'discount_rate']);
        });
    }
};
