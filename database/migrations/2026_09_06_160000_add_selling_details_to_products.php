<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of what a shopkeeper needs to describe and sell one thing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // A sentence or two: shown under the name, and used as the summary
            // search engines display.
            $table->string('short_description', 500)->nullable()->after('description');

            // Words a shopper might search for.
            $table->jsonb('tags')->nullable()->after('meta_description');

            // A YouTube link. Only the video's id is ever used when showing it.
            $table->string('video_url')->nullable()->after('tags');

            // What delivery costs for this product. Null means use the shop's
            // usual charge, 0 means delivery is free.
            $table->bigInteger('shipping_charge_minor')->nullable()->after('video_url');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('length_mm')->nullable()->after('weight_grams');
            $table->unsignedInteger('width_mm')->nullable()->after('length_mm');
            $table->unsignedInteger('height_mm')->nullable()->after('width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'tags', 'video_url', 'shipping_charge_minor']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['length_mm', 'width_mm', 'height_mm']);
        });
    }
};
