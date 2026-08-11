<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'po_number' => fake()->unique()->numerify('#######'),
            'vendor_id' => Vendor::factory(),
            'vendor_name' => fake()->company(),
            'po_date' => fake()->dateTimeBetween('-1 year'),
            'status' => fake()->randomElement(['APPROVED CLOSED', 'APPROVED']),
            'buyer_name' => fake()->name(),
        ];
    }
}
