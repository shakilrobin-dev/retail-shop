<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products sold by a measurement such as sqft (tiles), rft or kg.
 * Stock stays in pieces; the measurement is used to work out pieces and the bill.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            /** e.g. "sqft". Null = normal product. */
            $table->string('measure_unit', 20)->nullable()->after('unit_id');
            /** How much of the measurement one base unit (piece) has, e.g. 1.77 sqft. */
            $table->decimal('measure_per_unit', 12, 4)->nullable()->after('measure_unit');
            /** Selling rate per measurement, e.g. 120 per sqft. */
            $table->decimal('measure_price', 12, 2)->nullable()->after('measure_per_unit');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('measure_unit', 20)->nullable()->after('unit_price');
            /** Billed measurement (whole pieces × measurement per piece). */
            $table->decimal('measure_quantity', 14, 3)->nullable()->after('measure_unit');
            $table->decimal('measure_rate', 12, 2)->nullable()->after('measure_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['measure_unit', 'measure_quantity', 'measure_rate']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['measure_unit', 'measure_per_unit', 'measure_price']);
        });
    }
};
