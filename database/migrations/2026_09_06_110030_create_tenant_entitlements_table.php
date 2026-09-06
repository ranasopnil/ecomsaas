<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->boolean('enabled')->default(true);
            $table->integer('limit_value')->nullable();
            // 'package' rows are rewritten whenever the plan changes.
            // 'override' rows are set by a super admin and are left alone.
            $table->string('source')->default('package');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature']);
            $table->index(['tenant_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_entitlements');
    }
};
