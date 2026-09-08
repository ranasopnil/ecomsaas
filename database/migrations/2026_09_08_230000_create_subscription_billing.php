<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paying for the platform: renewals, plan changes, and extras bought on top.
 *
 * Money here is a whole number of the smallest unit with its currency beside
 * it, and a price a shop agreed to is never edited. A plan change ends one
 * subscription and starts another; what a shop paid stays exactly as it was
 * written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // A downgrade is booked for the renewal date rather than taking
            // effect at once, so a shop keeps what it paid for until then.
            $table->foreignId('scheduled_package_id')->nullable()->after('package_id')
                ->constrained('packages')->nullOnDelete();
            $table->timestamp('scheduled_change_at')->nullable()->after('scheduled_package_id');

            // How long the shop has after a missed renewal before its
            // dashboard closes. Its storefront is never touched.
            $table->timestamp('grace_ends_at')->nullable()->after('current_period_ends_at');
            $table->timestamp('locked_at')->nullable()->after('grace_ends_at');
        });

        // What a shop says it paid the platform, and what staff made of it.
        // Nothing here charges anybody: the money moves by bKash or bank, and
        // this is the record of it either side of that.
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedTinyInteger('currency_exponent');

            // Why it was owed: a renewal, the difference on an upgrade, or an
            // extra bought on top.
            $table->string('purpose')->default('renewal');

            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();

            $table->string('status')->default('claimed');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('decision_note')->nullable();

            // Which stretch of time it bought, once it is confirmed.
            $table->timestamp('covers_from')->nullable();
            $table->timestamp('covers_to')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'subscription_id']);
        });

        // Extras a shop can buy on top of its plan. Owned by the platform,
        // like the plans themselves.
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();

            // 'units'  — more of something counted, unit_amount at a time
            // 'switch' — one feature turned on
            $table->string('kind');
            $table->string('feature');
            $table->integer('unit_amount')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Priced separately in every market, exactly as plans are.
        Schema::create('addon_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedTinyInteger('currency_exponent');
            $table->bigInteger('price_minor');
            $table->timestamps();

            $table->unique(['addon_id', 'currency']);
        });

        // What one shop has bought, at the price agreed when it bought it.
        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->restrictOnDelete();

            $table->unsignedSmallInteger('quantity')->default(1);

            $table->bigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedTinyInteger('currency_exponent');

            $table->string('status')->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_addons');
        Schema::dropIfExists('addon_prices');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('subscription_payments');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_package_id');
            $table->dropColumn(['scheduled_change_at', 'grace_ends_at', 'locked_at']);
        });
    }
};
