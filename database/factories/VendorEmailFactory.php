<?php

namespace Database\Factories;

use App\Models\Vendor;
use App\Models\VendorEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorEmail>
 */
class VendorEmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
