<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shop's own book of money in and money out.
 *
 * Every row is written once and never changed. A mistake is put right by
 * writing the opposite row beside it, pointing back at the one it undoes —
 * the same rule the payments already follow, for the same reason: a shop
 * has to be able to see what actually happened, not what somebody later
 * wished had happened.
 *
 * Amounts are whole numbers of the smallest unit with their currency, never
 * decimals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // The day the money actually moved, which is not always the day
            // it was typed in.
            $table->date('occurred_on');

            // 'in' or 'out', so a total is a sum and never a guess about sign.
            $table->string('direction', 3);
            $table->string('kind');

            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('currency_exponent');

            $table->string('description');

            // What it came from, when it came from something.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('payment_id')->nullable();

            // "payment:12", "refund:5" — the one thing this row banks. Two
            // rows can never name the same thing, so a payment we are told
            // about twice is only ever entered once. Left empty for anything
            // a person wrote by hand, which has nothing to be twice of.
            $table->string('source_key')->nullable();

            // The row this one undoes, when it is a correction.
            $table->unsignedBigInteger('reverses_id')->nullable();

            // Who wrote it. The name is kept beside the id because a member of
            // staff may leave, and the book still has to read properly.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'occurred_on', 'id']);
            $table->index(['tenant_id', 'kind']);
            $table->index(['tenant_id', 'order_id']);

            $table->unique(['tenant_id', 'source_key']);
        });

        // What the courier still owes the shop on a cash-on-delivery order,
        // kept on the order so a list of them does not have to add up the
        // book for every row.
        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('cod_received_minor')->default(0)->after('not_delivered_reason');
            $table->timestamp('cod_received_at')->nullable()->after('cod_received_minor');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cod_received_minor', 'cod_received_at']);
        });

        Schema::dropIfExists('ledger_entries');
    }
};
