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
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->unique()->nullable();
            $table->boolean('sellable')->default(false);
            $table->boolean('purchasable')->default(false);
            $table->foreignId('income_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // income account e.g. sales/invoice
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete(); // expense account e.g. purchase/bill
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // $table->dropForeign(['income_account_id', 'expense_account_id']);
            $table->dropColumn(['sku', 'sellable', 'purchasable', 'income_account_id', 'expense_account_id']);
        });
    }
};
