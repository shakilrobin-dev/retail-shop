<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiles are bought by sqft too: the line keeps the sqft and the rate per sqft.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->string('measure_unit', 20)->nullable()->after('unit_cost');
            $table->decimal('measure_quantity', 14, 3)->nullable()->after('measure_unit');
            $table->decimal('measure_rate', 12, 2)->nullable()->after('measure_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['measure_unit', 'measure_quantity', 'measure_rate']);
        });
    }
};
