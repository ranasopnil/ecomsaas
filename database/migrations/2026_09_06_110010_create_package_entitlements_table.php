<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->boolean('enabled')->default(true);
            // Null means no ceiling. Only meaningful for counted features.
            $table->integer('limit_value')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_entitlements');
    }
};
