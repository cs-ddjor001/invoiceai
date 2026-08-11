<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendors are seeded from the ADS CSVs, where each row carries a PO_VENDOR_ID and a
 * PO_VENDOR_NAME. `external_id` holds that upstream id so the seeder can `upsert()` on it
 * across repeated runs without creating duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // The ADS-side vendor id. Nullable because a vendor can also be created from an
            // extracted invoice, where we only ever learn the name.
            $table->unsignedBigInteger('external_id')->nullable()->unique();
            $table->string('name');

            // The AP rep this vendor is assigned to. nullOnDelete keeps vendors alive when a
            // user is removed — losing an employee must not orphan the purchase history.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
