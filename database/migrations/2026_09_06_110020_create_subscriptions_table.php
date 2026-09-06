<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('trialing');

            // The price agreed at the time. A later price change on the package
            // must never alter what this store was charged.
            $table->bigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedTinyInteger('currency_exponent');
            $table->string('billing_period');

            $table->timestamp('starts_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
