<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a price is the price of.
 *
 * A grocer sells rice by the kilo, eggs by the dozen and shampoo by the
 * bottle. Showing "৳120" without saying what for makes a shop impossible to
 * compare or trust. The shopkeeper types it when adding the product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit', 40)->nullable()->after('short_description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
