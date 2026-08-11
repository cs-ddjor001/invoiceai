<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observability on the extraction pipeline: one row per attempt, holding the model's verbatim
 * response. When extraction produces garbage this is the only way to tell whether the model
 * or the parser was at fault. Phase 7's trainer screen reads from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Values match the Phase 3 driver names: pdf_text, llm, vision.
            $table->string('extractor', 20);

            // nullable: a run that threw has an `error` but no response to record.
            $table->json('raw_response')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_runs');
    }
};
