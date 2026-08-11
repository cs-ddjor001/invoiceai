<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 50);
        $unitPrice = fake()->randomFloat(4, 5, 2000);

        return [
            'invoice_id' => Invoice::factory(),
            'line_num' => fake()->numberBetween(1, 20),
            'part_number' => fake()->bothify(strtoupper(fake()->randomElement([
                '???####-???-##',
                '#######',
            ]))),
            'description' => strtoupper(rtrim(fake()->sentence(4), '.')),
            'uom' => fake()->randomElement(['Each', 'Box', 'Case', 'Lot']),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'amount' => round($qty * $unitPrice, 2),
            'clin' => fake()->optional()->numerify('####'),
        ];
    }
}
