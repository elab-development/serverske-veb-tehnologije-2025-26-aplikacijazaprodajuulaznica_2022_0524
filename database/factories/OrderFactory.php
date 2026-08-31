<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 6);
        $unitPrice = fake()->randomFloat(2, 10, 250);
        $status = fake()->randomElement(Order::STATUSES);

        return [
            'user_id' => User::factory(),
            'ticket_type_id' => TicketType::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => round($unitPrice * $quantity, 2),
            'status' => $status,
            'queue_number' => fake()->unique()->numberBetween(1, 1_000_000_000),
            'purchased_at' => $status === Order::STATUS_PAID
                ? fake()->dateTimeBetween('-2 months', 'now')
                : null,
        ];
    }
}
