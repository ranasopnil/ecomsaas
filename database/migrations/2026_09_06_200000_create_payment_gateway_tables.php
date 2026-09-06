<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which gateway is allowed in which country. Platform-owned: staff
        // decide this for everyone. A missing row means "as the code says".
        Schema::create('gateway_availabilities', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->char('country_code', 2);
            $table->boolean('is_allowed');
            $table->timestamps();

            $table->unique(['gateway', 'country_code']);
        });

        // A gateway handed to one shop by staff, regardless of its country.
        Schema::create('tenant_gateway_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->foreignId('granted_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway']);
        });

        // A shop's own account with a gateway. Secrets are encrypted and are
        // only ever shown back as their last four characters.
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('display_name')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway']);
            $table->index(['tenant_id', 'is_enabled', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('tenant_gateway_grants');
        Schema::dropIfExists('gateway_availabilities');
    }
};
