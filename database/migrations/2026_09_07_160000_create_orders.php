<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders.
 *
 * An order is a record of what was agreed at one moment: what was bought, at
 * what price, to be delivered where, for how much. None of that is ever
 * rewritten afterwards. If something changes — a refund, a cancellation — it
 * becomes a new row somewhere else pointing back here.
 *
 * Every line keeps its own copy of the name, the unit and the price. A
 * shopkeeper renaming a product or putting its price up must never change what
 * a customer was charged last week.
 */
return new class extends Migration
{
    public function up(): void
    {
        // What the shop charges to reach one of its areas. Zero is free, which
        // is different from not having decided.
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->bigInteger('delivery_charge_minor')->default(0)->after('radius_km');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // What the customer quotes, and a secret that lets them open their
            // own order without an account. A reference alone must not be
            // enough to read somebody else's order.
            $table->string('reference');
            $table->string('view_token', 40);

            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('customer_address');
            $table->text('customer_note')->nullable();

            // Which area it goes to, and its name at the time. The area may be
            // renamed or removed later; this order still says where it went.
            $table->foreignId('delivery_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_area_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('payment_gateway');
            $table->unsignedBigInteger('payment_id')->nullable();

            // Money, as whole numbers of the smallest unit, with the currency
            // and how many decimals it has. Never a decimal, never a float.
            $table->bigInteger('goods_minor');
            $table->bigInteger('delivery_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('currency_exponent');

            $table->string('status')->default('pending_payment');
            $table->string('payment_status')->default('unpaid');

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'customer_phone']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Kept loosely: a product withdrawn from sale must not take the
            // record of what somebody bought with it.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->string('unit', 40)->nullable();

            $table->bigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->bigInteger('line_total_minor');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('currency_exponent');

            $table->timestamps();

            $table->index(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');

        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->dropColumn('delivery_charge_minor');
        });
    }
};
