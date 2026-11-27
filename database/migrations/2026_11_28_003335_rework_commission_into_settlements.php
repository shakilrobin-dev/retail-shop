<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission is no longer set on each invoice. The invoice only keeps the reference person;
 * commission is worked out later on the person's total profit for a month or date range.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['commission_type', 'commission_value', 'commission_amount']);
        });

        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained()->restrictOnDelete();
            /** Branch where the commission was worked out. */
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            /** Snapshot of the referred sales in the period. */
            $table->unsignedInteger('invoices')->default(0);
            $table->decimal('sales', 14, 2)->default(0);
            $table->decimal('profit', 14, 2)->default(0);
            /** profit_percent or amount */
            $table->string('commission_type', 20);
            $table->decimal('commission_value', 14, 2);
            /** Commission given to the person for this period. */
            $table->decimal('amount', 14, 2);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['referrer_id', 'period_from', 'period_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_settlements');

        Schema::table('sales', function (Blueprint $table) {
            $table->string('commission_type', 20)->nullable()->after('referrer_id');
            $table->decimal('commission_value', 14, 2)->default(0)->after('commission_type');
            $table->decimal('commission_amount', 14, 2)->default(0)->after('commission_value');
        });
    }
};
