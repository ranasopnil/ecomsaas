<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos of a product.
 *
 * An image with no variant belongs to the whole product. An image with a
 * variant is the picture for that one combination — the red shirt, not the
 * blue one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'position']);
            $table->index(['tenant_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
