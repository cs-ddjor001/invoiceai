<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per invoice, however it arrived. Match results live in `invoice_matches` — this
 * table deliberately records no matched PO of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number', 50)->nullable();
            $table->string('po_number', 20)->nullable();

            // nullable + nullOnDelete: extraction often runs before the vendor is resolved,
            // and removing a vendor must never destroy the invoices billed against them.
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vendor_name')->nullable();

            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('subtotal', 14, 2)->nullable();
            $table->decimal('tax', 14, 2)->nullable();

            $table->date('issued_at')->nullable();

            // string + a PHP enum on the model, never $table->enum(). See the note in the
            // review: native ENUM needs a migration to add a value and behaves differently
            // on SQLite, which the test suite runs on.
            $table->string('status', 20)->default('pending');
            $table->string('source', 20)->default('upload');

            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->string('file_path')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Two single-column indexes, not one composite: these columns are filtered
            // independently and never together. See the review for the leftmost-prefix rule.
            $table->index('status');
            $table->index('po_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
