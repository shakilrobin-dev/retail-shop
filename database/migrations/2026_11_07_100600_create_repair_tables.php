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
        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('job_no', 30)->unique();
            $table->string('status', 30);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            /** Set when this job is a warranty claim of an earlier job. */
            $table->foreignId('warranty_of_job_id')->nullable()->constrained('repair_jobs')->nullOnDelete();

            $table->string('device_type', 30);
            $table->string('device_brand', 50)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('device_color', 30)->nullable();
            $table->string('imei_serial', 50)->nullable();
            /** Stored encrypted. */
            $table->text('device_password')->nullable();
            $table->json('accessories')->nullable();
            $table->text('condition_notes')->nullable();
            $table->text('problem');

            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->decimal('service_charge', 14, 2)->default(0);
            $table->decimal('parts_total', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);

            $table->unsignedInteger('warranty_days')->nullable();
            $table->date('warranty_until')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('imei_serial');
        });

        Schema::create('repair_job_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_quantity', 12, 3);
            $table->decimal('base_quantity', 14, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('repair_job_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_job_status_histories');
        Schema::dropIfExists('repair_job_parts');
        Schema::dropIfExists('repair_jobs');
    }
};
