<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item discounts as consecutive percentages ("20,10,5"), and whether the invoice discount
     * applies to the item (with the item's share of it, used for returns).
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('discount_percents', 60)->nullable()->after('discount_amount');
            $table->boolean('bill_discount')->default(true)->after('discount_percents');
            $table->decimal('bill_discount_amount', 14, 2)->default(0)->after('bill_discount');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('discount_percents', 60)->nullable()->after('discount_amount');
            $table->boolean('bill_discount')->default(true)->after('discount_percents');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['discount_percents', 'bill_discount', 'bill_discount_amount']);
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['discount_percents', 'bill_discount']);
        });
    }
};
