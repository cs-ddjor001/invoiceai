<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The subject stays null here. A morph has no fixed target, so there is no honest default
     * — guessing Invoice would make every log about invoices. Attach one at the call site:
     *
     *     ActivityLog::factory()->for($invoice, 'subject')->create();
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'action' => fake()->randomElement([
                'invoice.uploaded',
                'invoice.extracted',
                'match.accepted',
                'match.rejected',
                'vendor.assigned',
            ]),
        ];
    }

    /**
     * A log entry with no attributed user — what remains after the account is deleted.
     */
    public function orphaned(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
