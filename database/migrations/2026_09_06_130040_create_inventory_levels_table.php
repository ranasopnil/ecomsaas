<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();

            // What can still be sold, and what is spoken for by orders in flight.
            $table->integer('available')->default(0);
            $table->integer('reserved')->default(0);

            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_backorder')->default(false);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_variant_id']);
        });

        // Every change to stock, kept as a list of what happened. Rows are
        // never edited: a correction is another row.
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_change');
            $table->integer('available_after');
            $table->string('reason');
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'product_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_levels');
    }
};
