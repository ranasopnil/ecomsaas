<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('pending');
            $table->string('email')->nullable();
            $table->char('country_code', 2)->default('BD');
            $table->char('currency', 3)->default('BDT');
            $table->unsignedTinyInteger('currency_exponent')->default(2);
            $table->string('timezone')->default('Asia/Dhaka');
            $table->boolean('prices_include_tax')->default(false);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
