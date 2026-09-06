<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->bigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->unsignedTinyInteger('currency_exponent')->default(2);
            $table->string('billing_period')->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_public', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
