<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantityTotal = fake()->numberBetween(50, 500);

        return [
            'event_id' => Event::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 10, 250),
            'quantity_total' => $quantityTotal,
            'quantity_available' => fake()->numberBetween(0, $quantityTotal),
            'max_per_order' => fake()->numberBetween(1, 10),
        ];
    }
}
