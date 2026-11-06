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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /** Base units of measure: piece, meter, set... */
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('short_name', 10);
            $table->boolean('allows_decimal')->default(false);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('compatible_models')->nullable();
            $table->text('description')->nullable();
            /** Average cost of ONE base unit, recalculated on every purchase. */
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('min_selling_price', 12, 2)->nullable();
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->unsignedInteger('warranty_days')->nullable();
            /** Reserved for Phase 3 (appliances with serial numbers). */
            $table->boolean('is_serialized')->default(false);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        /**
         * Selling units of a product. Every product has one base unit row
         * (quantity = 1) and optional packs, e.g. "Box" with quantity = 10.
         */
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('name_bn', 50)->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('selling_price', 12, 2);
            $table->boolean('is_base')->default(false);
            $table->timestamps();
        });

        /** A product unit can have several barcodes (maker code + our own code). */
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->boolean('is_generated')->default(false);
            $table->timestamps();
        });

        /** Optional per-branch price override for a product unit. */
        Schema::create('branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('selling_price', 12, 2);
            $table->timestamps();

            $table->unique(['branch_id', 'product_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_prices');
        Schema::dropIfExists('barcodes');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
