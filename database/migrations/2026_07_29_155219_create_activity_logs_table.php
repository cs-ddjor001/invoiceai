<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail. Polymorphic on `subject` so the log can cover invoices, POs and vendors
 * without a schema change — a generalization past the plan's invoice-only design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // nullOnDelete, never cascade: an audit trail that disappears when you delete the
            // user is not an audit trail. The row survives, attributed to nobody.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Replaces the invoice_id FK — subject_type + subject_id cover invoices and
            // everything else. nullableMorphs() also creates the composite index they need.
            $table->nullableMorphs('subject');

            $table->string('action');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
