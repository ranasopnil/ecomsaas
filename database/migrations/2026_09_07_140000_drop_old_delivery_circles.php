<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The contract half of the move to named delivery areas.
     *
     * Areas used to be one unnamed circle on the shop plus one more circle on
     * each product. The previous deploy copied both into `delivery_areas` and
     * stopped reading these columns. Nothing has read them since, so they go.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['delivery_latitude', 'delivery_longitude', 'delivery_radius_km']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_km']);
        });
    }

    /**
     * Puts the columns back, empty. The circles themselves live on as named
     * areas in `delivery_areas`; only these unused copies are gone.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->decimal('delivery_radius_km', 6, 2)->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('radius_km', 6, 2)->nullable();
        });
    }
};
