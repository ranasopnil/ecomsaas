<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What platform staff did across shops.
 *
 * Almost everything in this system is fenced inside one shop. The few places
 * staff must reach across that fence — confirming that a shop's payment
 * arrived, moving its plan — are written down here, with who did it and to
 * whom. Rows are added and never changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            // Kept beside the id because staff leave and the record still has
            // to read properly.
            $table->string('admin_name')->nullable();

            // Which shop it was done to, when it was done to one.
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('tenant_name')->nullable();

            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('note')->nullable();
            $table->jsonb('meta')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
