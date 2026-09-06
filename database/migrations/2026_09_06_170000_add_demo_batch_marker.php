<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks rows that were put in as sample data.
 *
 * Without this, removing a sample shop would mean guessing which rows were
 * ours and which the shopkeeper typed. Anything the shopkeeper creates has
 * this empty, and is never touched by the removal.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'categories', 'brands'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('demo_batch')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'categories', 'brands'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('demo_batch');
            });
        }
    }
};
