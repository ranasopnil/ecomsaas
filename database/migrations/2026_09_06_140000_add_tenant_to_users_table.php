<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant accounts belong to one shop.
 *
 * An email address can therefore be used by one person at their own shop and
 * by someone else at a different shop, which is how a marketplace has to work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable();

            $table->dropUnique('users_email_unique');
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['role', 'is_active', 'last_login_at']);
            $table->unique('email');
        });
    }
};
