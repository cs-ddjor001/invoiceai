<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The addresses a vendor sends invoices from. Phase 6 resolves an incoming message to a
 * vendor by looking the sender up here, which is why `email` is unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_emails', function (Blueprint $table) {
            $table->id();

            // cascade, not nullOnDelete: an address with no vendor has nothing to resolve to.
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            // unique() also creates the index Phase 6 looks the sender up by.
            $table->string('email')->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_emails');
    }
};
