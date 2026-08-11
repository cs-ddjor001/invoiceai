<?php

namespace Database\Factories;

use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 25000);
        $tax = round($subtotal * 0.06, 2);

        return [
            // Deliberately shapeless. Sampling the 39 real ADS invoices found no common
            // format — 39 vendors, 39 numbering schemes: 13983, DEF037692, 0002473-IN,
            // 25-IN02001, INV050-008681, 5951865995. Generating a tidy INV-0000-abc would
            // make tests pass against an assumption production does not share.
            'invoice_number' => fake()->unique()->randomElement([
                fake()->numerify('#####'),
                fake()->numerify('##########'),
                fake()->bothify('???######'),
                fake()->numerify('#######').'-IN',
                fake()->numerify('##').'-IN'.fake()->numerify('#####'),
                'INV'.fake()->numerify('###').'-'.fake()->numerify('######'),
            ]),

            // The PO side is the opposite: ADS issues these, and all 17 sampled invoices
            // carried exactly 7 digits. The rule is real, so encode it.
            'po_number' => fake()->numerify('#######'),

            // Passing a factory rather than an id lets Eloquent create the parent on demand.
            // Nothing runs until the invoice is actually persisted.
            'vendor_id' => Vendor::factory(),
            'vendor_name' => fake()->company(),

            'subtotal' => $subtotal,
            'tax' => $tax,
            'amount' => $subtotal + $tax,

            'issued_at' => fake()->dateTimeBetween('-1 year'),
            'status' => InvoiceStatus::Pending,
            'source' => InvoiceSource::Upload,
            'quality_score' => fake()->numberBetween(60, 100),
            'file_path' => 'invoices/'.fake()->uuid().'.pdf',
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * States are named, reusable overrides. `Invoice::factory()->matched()->create()` reads
     * as the scenario under test instead of a bag of attribute overrides.
     */
    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Matched,
        ]);
    }

    /**
     * An invoice that arrived through the Phase 6 mail intake rather than an upload.
     */
    public function fromEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => InvoiceSource::Email,
            'uploaded_by' => null,
        ]);
    }

    /**
     * The realistic bad case: extraction ran but found almost nothing. Phase 4 needs these to
     * prove low-quality invoices score poorly rather than matching by accident.
     */
    public function unextractable(): static
    {
        return $this->state(fn (array $attributes) => [
            'po_number' => null,
            'invoice_number' => null,
            'amount' => null,
            'subtotal' => null,
            'tax' => null,
            'issued_at' => null,
            'status' => InvoiceStatus::Failed,
            'quality_score' => fake()->numberBetween(0, 40),
        ]);
    }
}
