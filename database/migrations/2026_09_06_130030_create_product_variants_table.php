<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'name']);
        });

        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_option_id', 'value']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();

            // Money is whole units of the smallest coin, with its currency.
            $table->bigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedTinyInteger('currency_exponent');
            $table->bigInteger('compare_at_price_minor')->nullable();
            $table->bigInteger('cost_price_minor')->nullable();

            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'product_id', 'position']);
        });

        // A shop may reuse a code another shop uses; within one shop it must
        // be unique. Rows without a code are not compared.
        DB::statement('CREATE UNIQUE INDEX product_variants_tenant_sku_unique ON product_variants (tenant_id, sku) WHERE sku IS NOT NULL AND deleted_at IS NULL');

        Schema::create('product_option_value_variant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();

            $table->unique(['tenant_id', 'product_variant_id', 'product_option_value_id'], 'variant_option_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_value_variant');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
    }
};
