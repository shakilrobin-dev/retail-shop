<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credit (due) sales, bill rounding, customer credit limits and due collections.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('rounding_amount', 12, 2)->default(0)->after('vat_amount');
            $table->decimal('due_amount', 12, 2)->default(0)->after('change_amount');
            $table->string('payment_status', 10)->default('paid')->after('due_amount')->index();
            $table->date('due_date')->nullable()->after('payment_status');
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->decimal('refund_amount', 12, 2)->default(0)->after('total');
        });

        /** Before credit sales every return was refunded in full. */
        DB::table('sale_returns')->update(['refund_amount' => DB::raw('total')]);

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->nullable()->after('address');
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('receipt_no')->unique();
            $table->foreignId('payment_method_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('transaction_ref')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->index();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('customer_payment_id')->nullable()->after('payable_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_payment_id');
        });

        Schema::dropIfExists('customer_payments');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('refund_amount');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['rounding_amount', 'due_amount', 'payment_status', 'due_date']);
        });
    }
};
