<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a payment was asked for, beyond its amount.
 *
 * An extra bought on top of a plan is not granted until the money is found,
 * so the request has to be remembered somewhere until then. This is that
 * somewhere: which add-on, and how many.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->jsonb('meta')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
