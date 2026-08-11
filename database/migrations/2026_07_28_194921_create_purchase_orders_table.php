<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per distinct PO_NUMBER in the ADS CSVs. The CSV is line-item shaped, so the header
 * fields below repeat across every row sharing a PO number — the seeder collapses them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // string, not integer — see the decisions table in REBUILD_PLAN.md §3. The
            // "exactly 7 digits" rule is validation, enforced in the Phase 3 DTO, not here.
            // unique() creates the index, so no separate index() call is needed.
            $table->string('po_number', 20)->unique();

            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();

            // Deliberately denormalized alongside vendor_id: matching scores the invoice's
            // extracted vendor text against this string, and it must reflect what the PO
            // actually said even if the vendor record is later renamed or merged.
            $table->string('vendor_name')->nullable();

            $table->date('po_date')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('buyer_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
