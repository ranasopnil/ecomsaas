<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per shop per day. This is the only lasting record of
        // visitors, and it holds counts only — nothing about who visited.
        Schema::create('visit_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('on_day');
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'on_day']);
        });

        // Who is looking at the shop at this moment, so "right now" can be
        // counted. A row is a one-way fingerprint that changes every day, not
        // an address and not a name. Rows are thrown away after a day.
        Schema::create('visit_pulses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->timestamp('seen_at');

            $table->unique(['tenant_id', 'visitor_hash']);
            $table->index(['tenant_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_pulses');
        Schema::dropIfExists('visit_days');
    }
};
