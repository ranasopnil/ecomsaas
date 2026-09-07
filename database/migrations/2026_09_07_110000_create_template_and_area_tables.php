<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which shop front each plan includes. Platform-owned: staff decide it
        // for everyone, the same way gateway availability works.
        Schema::create('package_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('template');
            $table->timestamps();

            $table->unique(['package_id', 'template']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            // Null means the shop has not chosen, and gets the plain one.
            $table->string('template')->nullable();
            $table->jsonb('template_settings')->nullable();

            // Which map this shop draws its delivery area on. Staff set it,
            // because one of the two costs the platform money per map opened.
            $table->string('map_provider')->nullable();

            // The shop's own delivery area, which its products use unless a
            // product says otherwise.
            $table->boolean('delivers_everywhere')->default(true);
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->decimal('delivery_radius_km', 6, 2)->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            // 'shop'     — wherever the shop delivers (the default)
            // 'anywhere' — no limit, even if the shop has an area
            // 'area'     — this product's own point and radius
            $table->string('availability')->default('shop');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('radius_km', 6, 2)->nullable();

            // Narrowing a shop's catalogue by where the customer is has to stay
            // quick, and the search always starts from one shop.
            $table->index(['tenant_id', 'availability']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'availability']);
            $table->dropColumn(['availability', 'latitude', 'longitude', 'radius_km']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'template', 'template_settings', 'map_provider',
                'delivers_everywhere', 'delivery_latitude', 'delivery_longitude', 'delivery_radius_km',
            ]);
        });

        Schema::dropIfExists('package_templates');
    }
};
