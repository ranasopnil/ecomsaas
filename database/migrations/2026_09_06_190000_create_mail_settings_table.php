<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shop's own outgoing email server, so its emails come from its own
 * address rather than from the platform.
 *
 * The password is encrypted at rest and is never shown again once saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption')->default('tls');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address');
            $table->string('from_name');
            $table->boolean('is_enabled')->default(false);
            $table->string('status')->default('untested');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
