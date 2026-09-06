<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A plan is priced separately in every market it is sold in. A single price
 * converted at today's exchange rate is not good enough: 990 Taka and its
 * conversion into Ringgit are different commercial decisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedTinyInteger('currency_exponent');
            $table->bigInteger('price_minor');
            $table->string('billing_period')->default('monthly');
            $table->timestamps();

            $table->unique(['package_id', 'currency']);
        });

        // Carry across the single price each plan already had.
        foreach (DB::table('packages')->get() as $package) {
            DB::table('package_prices')->insert([
                'package_id' => $package->id,
                'currency' => $package->currency,
                'currency_exponent' => $package->currency_exponent,
                'price_minor' => $package->price_minor,
                'billing_period' => $package->billing_period,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_prices');
    }
};
