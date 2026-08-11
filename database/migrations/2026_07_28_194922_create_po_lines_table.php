<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per line in the ADS CSVs. `part_number` and `unit_price` are the two columns the
 * Phase 4 matcher actually reads — the deterministic path requires a part-number hit whose
 * unit price falls inside the tolerance tier, so both are indexed for candidate prefiltering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_lines', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete, unlike vendors above: a line has no meaning without its PO,
            // so deleting the parent should take the children with it.
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('line_num')->nullable();
            $table->string('part_number', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('uom', 50)->nullable();

            // decimal, never float. The source app used db.Float for money, which is how you
            // get 292.27 back as 292.26999999. Quantities get 4dp because UOM is not always
            // "Each". Note: Eloquent's decimal cast returns a *string*, so Phase 4 comparisons
            // must cast explicitly rather than relying on PHP's loose numeric juggling.
            $table->decimal('qty_ordered', 12, 4)->nullable();
            $table->decimal('qty_delivered', 12, 4)->nullable();
            $table->decimal('qty_cancelled', 12, 4)->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->decimal('amt_invoiced', 14, 2)->nullable();

            $table->string('status', 50)->nullable();

            // 255, not 50: a single ADS line can cover several contract line item numbers and
            // the CSV crams them into one field — PO 1481497 line 1 carries
            // "0029, 0029, 0029, 0029, 0029, 0029, 0029, 0029, 0029" at 52 characters.
            $table->string('clin', 255)->nullable();

            $table->timestamps();

            // Composite, ordered most-selective-first: the matcher looks up candidate lines by
            // part number and then compares price, so one index serves both steps.
            $table->index(['part_number', 'unit_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_lines');
    }
};
