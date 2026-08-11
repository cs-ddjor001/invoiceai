<?php

namespace Database\Factories;

use App\Enums\MatchStrategy;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceMatch>
 */
class InvoiceMatchFactory extends Factory
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
            'purchase_order_id' => PurchaseOrder::factory(),
            'strategy' => fake()->randomElement(MatchStrategy::cases()),
            'confidence' => fake()->randomFloat(4, 0, 1),
            // not sure about this one, it says reasoning → an array (the 'array' cast handles JSON encoding), but not sure what that means, left it null for now
            'reasoning' => null,
            'is_accepted' => fake()->boolean(),
            'reviewed_by' => User::factory(),
        ];
    }
}
