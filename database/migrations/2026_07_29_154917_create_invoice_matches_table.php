<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (invoice, PO, strategy). Replaces the source app's triple bookkeeping —
 * `matched_po_id` + `ai_matched_po_id` + a `match` table — and keeps the deterministic
 * vs. AI comparison the project was built to make.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();

            // NOT nullable, because it participates in the unique index below: MySQL treats
            // NULLs as distinct, so a nullable column silently defeats the constraint.
            $table->string('strategy', 20);

            // decimal, not integer — the internal scale is 0..1 and rounding to a whole
            // number would collapse every score into 0 or 1.
            $table->decimal('confidence', 5, 4)->nullable();

            $table->json('reasoning')->nullable();
            $table->boolean('is_accepted')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Lets Phase 4 re-run matching with updateOrCreate() instead of the source app's
            // check-then-insert, which races.
            $table->unique(['invoice_id', 'purchase_order_id', 'strategy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_matches');
    }
};
