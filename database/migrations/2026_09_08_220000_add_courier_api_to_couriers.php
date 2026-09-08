<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Letting a shop talk to its courier, or not.
 *
 * A courier stays exactly as it was — a name on a list, with the consignment
 * number typed in by hand — unless the shopkeeper switches it to automatic
 * and enters their own account details. Then the parcel is booked with the
 * courier as the order is handed over, and the number comes back on its own.
 *
 * The details are the shop's property: encrypted as a whole, hidden from
 * every array and view, and shown afterwards only as their last four
 * characters. The same rule the payment gateways follow, for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            // Which of our own courier modules this is, when it is one of
            // them. Null means a courier we have no way of talking to, which
            // is perfectly usable: the shopkeeper types the number in.
            $table->string('driver')->nullable()->after('name');

            // 'manual' or 'automatic'. A courier with a driver can still be
            // manual, and starts that way.
            $table->string('mode')->default('manual')->after('driver');

            $table->text('credentials')->nullable()->after('mode');
            $table->jsonb('settings')->nullable()->after('credentials');
        });
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->dropColumn(['driver', 'mode', 'credentials', 'settings']);
        });
    }
};
