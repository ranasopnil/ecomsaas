<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a shop says about itself at the bottom of every page.
 *
 * The address, the phone number, where to find the shop elsewhere, and the
 * pages a customer expects to be able to read before buying — privacy,
 * refunds, delivery, terms. All of it typed by the shopkeeper on one screen.
 *
 * Everything here is plain text. Nothing a merchant types is ever run as
 * code or shown as markup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_footers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Who the shop is, and how to reach it.
            $table->text('about')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('opening_hours')->nullable();

            // Where else the shop can be found.
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('whatsapp_number')->nullable();

            // The pages a shopper expects to be able to read.
            $table->text('about_us')->nullable();
            $table->text('privacy_policy')->nullable();
            $table->text('refund_policy')->nullable();
            $table->text('shipping_policy')->nullable();
            $table->text('terms')->nullable();

            $table->string('copyright')->nullable();

            $table->timestamps();

            // One per shop, and an index that leads with tenant_id.
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_footers');
    }
};
