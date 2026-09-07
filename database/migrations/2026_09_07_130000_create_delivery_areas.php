<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Places a shop delivers to, each with a name the shopkeeper chose:
        // "Dhaka city", "Mirpur", "Uttara". Drawn once, then picked from a
        // list on every product.
        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('radius_km', 6, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // Two areas with the same name would be impossible to tell apart
            // in the list a shopkeeper picks from.
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'position']);
        });

        Schema::create('delivery_area_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->unique(['tenant_id', 'delivery_area_id', 'product_id']);
            $table->index(['tenant_id', 'product_id']);
        });

        $this->moveExistingAreasAcross();
    }

    /**
     * Keep what shops have already drawn.
     *
     * Areas used to be one unnamed circle on the shop plus one more per
     * product. Both become named areas, so nothing a shopkeeper set up is
     * lost. Raw queries on purpose: a migration runs before any shop is
     * bound, and this deliberately walks every shop in turn.
     */
    protected function moveExistingAreasAcross(): void
    {
        $now = now();

        // 1. Each shop's own circle becomes its first named area.
        $shops = DB::table('tenants')
            ->whereNotNull('delivery_latitude')
            ->whereNotNull('delivery_longitude')
            ->where('delivery_radius_km', '>', 0)
            ->where('delivers_everywhere', false)
            ->get(['id', 'delivery_latitude', 'delivery_longitude', 'delivery_radius_km']);

        foreach ($shops as $shop) {
            DB::table('delivery_areas')->insert([
                'tenant_id' => $shop->id,
                'name' => 'My delivery area',
                'latitude' => $shop->delivery_latitude,
                'longitude' => $shop->delivery_longitude,
                'radius_km' => $shop->delivery_radius_km,
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. Every circle drawn on a single product becomes a named area of
        //    its own, attached to that product. Identical circles are shared.
        $products = DB::table('products')
            ->where('availability', 'area')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('radius_km', '>', 0)
            ->get(['id', 'tenant_id', 'latitude', 'longitude', 'radius_km']);

        $numbered = [];

        foreach ($products as $product) {
            $signature = $product->tenant_id.'|'.$product->latitude.'|'.$product->longitude.'|'.$product->radius_km;

            if (! isset($numbered[$signature])) {
                $count = DB::table('delivery_areas')->where('tenant_id', $product->tenant_id)->count();

                $numbered[$signature] = DB::table('delivery_areas')->insertGetId([
                    'tenant_id' => $product->tenant_id,
                    'name' => 'Delivery area '.($count + 1),
                    'latitude' => $product->latitude,
                    'longitude' => $product->longitude,
                    'radius_km' => $product->radius_km,
                    'position' => $count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('delivery_area_product')->insert([
                'tenant_id' => $product->tenant_id,
                'delivery_area_id' => $numbered[$signature],
                'product_id' => $product->id,
            ]);
        }

        // 'area' meant "the circle drawn on me"; it now means "the areas
        // ticked on me". The old columns stay for one deploy, unused, so
        // this can be undone without losing anything.
        DB::table('products')->where('availability', 'area')->update(['availability' => 'areas']);
    }

    public function down(): void
    {
        DB::table('products')->where('availability', 'areas')->update(['availability' => 'area']);

        Schema::dropIfExists('delivery_area_product');
        Schema::dropIfExists('delivery_areas');
    }
};
