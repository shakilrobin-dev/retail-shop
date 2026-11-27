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
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('referrer_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            /** profit_percent or amount */
            $table->string('commission_type', 20)->nullable()->after('referrer_id');
            $table->decimal('commission_value', 14, 2)->default(0)->after('commission_type');
            /** Commission earned by the reference person on this sale. */
            $table->decimal('commission_amount', 14, 2)->default(0)->after('commission_value');
        });

        /** A commission payment is saved as an expense linked to the reference person. */
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('referrer_id')->nullable()->after('expense_category_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referrer_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referrer_id');
            $table->dropColumn(['commission_type', 'commission_value', 'commission_amount']);
        });
    }
};
