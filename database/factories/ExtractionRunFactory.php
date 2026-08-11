<?php

namespace Database\Factories;

use App\Enums\ExtractorType;
use App\Models\ExtractionRun;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtractionRun>
 */
class ExtractionRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'extractor' => ExtractorType::Llm,

            // Shaped like what the model actually returns, so Phase 3 can parse a factory-made
            // run without special-casing test data.
            'raw_response' => [
                'invoice_number' => fake()->bothify('INV-####'),
                'po_number' => fake()->numerify('#######'),
                'vendor_name' => fake()->company(),
                'total' => fake()->randomFloat(2, 100, 20000),
                'line_items' => [
                    [
                        'part_number' => fake()->bothify('???####'),
                        'description' => fake()->words(3, true),
                        'quantity' => fake()->numberBetween(1, 20),
                        'unit_price' => fake()->randomFloat(2, 10, 500),
                    ],
                ],
            ],

            'duration_ms' => fake()->numberBetween(800, 15000),
            'status' => 'completed',
            'error' => null,
        ];
    }

    /**
     * A run that threw. Note `raw_response` goes null — that pairing is exactly why the
     * column is nullable.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'raw_response' => null,
            'error' => fake()->randomElement([
                'Connection refused: http://localhost:8080/v1',
                'Model returned malformed JSON',
                'Request timed out after 60s',
            ]),
            'duration_ms' => fake()->numberBetween(60000, 90000),
        ]);
    }

    /**
     * The cheap text path rather than the LLM.
     */
    public function pdfText(): static
    {
        return $this->state(fn (array $attributes) => [
            'extractor' => ExtractorType::PdfText,
            'duration_ms' => fake()->numberBetween(20, 300),
        ]);
    }
}
