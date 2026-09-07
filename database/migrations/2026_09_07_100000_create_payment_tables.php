<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One attempt to take money from one customer. The amount is a whole
        // number of the smallest unit plus its currency, never a decimal.
        //
        // A payment is never edited into a refund: refunds are their own rows
        // in payment_refunds, pointing back here.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('reference');

            // Filled in when orders exist. Left loose on purpose: adding the
            // constraint later is a safe change, dropping a wrong one is not.
            $table->unsignedBigInteger('order_id')->nullable();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('currency_exponent');

            $table->string('status')->default('pending');
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('payer_reference')->nullable();
            $table->string('payer_account')->nullable();
            $table->string('failure_reason')->nullable();
            $table->boolean('is_sandbox')->default(false);
            $table->jsonb('meta')->nullable();

            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->unique(['tenant_id', 'gateway', 'gateway_payment_id']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'order_id']);
        });

        // Money given back. A new row every time — the payment above keeps
        // saying what was originally taken.
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('currency_exponent');

            $table->string('status')->default('pending');
            $table->string('gateway_refund_id')->nullable();
            $table->string('reason')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'payment_id']);
        });

        // Everything a gateway has told us, once. The unique key is what makes
        // a repeated message harmless: gateways resend, and they are meant to.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway');
            $table->string('event_id');
            $table->string('type');
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway', 'event_id']);
            $table->index(['tenant_id', 'payment_id']);
        });

        // Anything that must happen because money moved — an email, a stock
        // change — is written here in the same transaction as the payment. A
        // worker reads this table. A queue alone can lose a job; this cannot.
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->jsonb('payload')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'processed_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payments');
    }
};
