<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->integer('line_num')->nullable();
            $table->string('part_number', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('uom', 50)->nullable();
            $table->decimal('qty', 12, 4)->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            // Matches po_lines: CLIN can be a comma-separated list, not a single code.
            $table->string('clin', 255)->nullable();
            $table->timestamps();

            // invoice_id is already indexed by the foreign key constraint. This is the one
            // lookup the matcher actually performs on this table.
            $table->index('part_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
