<?php

namespace Database\Factories;

use App\Models\PoLine;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoLine>
 */
class PoLineFactory extends Factory
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
            'purchase_order_id' => PurchaseOrder::factory(),
            'line_num' => fake()->numberBetween(1, 20),

            // Shaped like the real ADS part numbers (FPR1010-ASA-K9, 4043119).
            'part_number' => fake()->bothify(strtoupper(fake()->randomElement([
                '???####-???-##',
                '#######',
            ]))),
            // sentence() is typed string; words() is declared array|string and can't be
            // narrowed, which is what PHPStan objects to.
            'description' => strtoupper(rtrim(fake()->sentence(4), '.')),
            'uom' => fake()->randomElement(['Each', 'Box', 'Case', 'Lot']),

            'qty_ordered' => $qty,
            'qty_delivered' => $qty,
            'qty_cancelled' => 0,

            // Derived, not independent: a line whose amount contradicts qty × price would be
            // useless for testing the matcher.
            'unit_price' => $unitPrice,
            'amt_invoiced' => round($qty * $unitPrice, 2),

            'status' => fake()->randomElement(['OPEN', 'CLOSED', 'APPROVED']),
            'clin' => fake()->optional()->numerify('####'),
        ];
    }

    /**
     * A line priced above the $5,000 threshold, where the tolerance tier tightens to 1%.
     * Phase 4 tests the tiers directly, so the factory should be able to hit each one.
     */
    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_price' => fake()->randomFloat(4, 5001, 50000),
        ]);
    }

    /**
     * A line under $100, where the tolerance widens to 5%.
     */
    public function lowValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_price' => fake()->randomFloat(4, 1, 99),
        ]);
    }
}
