<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moving an order along, and writing down every step.
 *
 * An order used to be either placed or cancelled. A shop needs more than
 * that: it has to approve an order, pack it, hand it to a courier, and say
 * what happened when it arrived — or did not.
 *
 * Nothing here changes what an order is worth. The totals agreed at checkout
 * are untouched; this is only about where the parcel has got to.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The couriers a shop actually uses, in its own words.
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();

            // Where a customer follows their parcel. {code} is replaced with
            // the consignment number the shopkeeper types in.
            $table->string('tracking_url')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // One shop cannot have two couriers of the same name, and every
            // index leads with the shop.
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'position']);
        });

        // Every step an order has taken, written down once and never edited.
        // This is the answer to "who moved this, when, and why".
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');

            // Why, in the shopkeeper's own words: a reason for turning an
            // order down, or for a parcel that could not be delivered.
            $table->text('note')->nullable();

            $table->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('courier_name')->nullable();
            $table->string('tracking_code')->nullable();

            // Who did it. The name is kept beside the id because a member of
            // staff may leave, and the history still has to read properly.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'order_id', 'id']);
        });

        // Where the parcel is now, kept on the order itself so a list of
        // orders does not have to read the history of every one of them.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('delivery_area_name')->constrained()->nullOnDelete();
            $table->string('courier_name')->nullable()->after('courier_id');
            $table->string('tracking_code')->nullable()->after('courier_name');

            $table->timestamp('approved_at')->nullable()->after('placed_at');
            $table->timestamp('handed_over_at')->nullable()->after('approved_at');
            $table->timestamp('delivered_at')->nullable()->after('handed_over_at');
            $table->string('not_delivered_reason')->nullable()->after('cancelled_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');
            $table->dropColumn([
                'courier_name', 'tracking_code',
                'approved_at', 'handed_over_at', 'delivered_at', 'not_delivered_reason',
            ]);
        });

        Schema::dropIfExists('order_events');
        Schema::dropIfExists('couriers');
    }
};
