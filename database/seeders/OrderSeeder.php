<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = [
            ['email' => 'marko.petrovic@example.com', 'event' => 'Belgrade Music Festival', 'ticket' => 'Regular', 'quantity' => 2, 'status' => Order::STATUS_PAID, 'queue_number' => 1001],
            ['email' => 'jovana.ilic@example.com', 'event' => 'Laravel Serbia Conference', 'ticket' => 'Student', 'quantity' => 1, 'status' => Order::STATUS_PAID, 'queue_number' => 1002],
            ['email' => 'nikola.savic@example.com', 'event' => 'Stand-up Night Novi Sad', 'ticket' => 'Front Row', 'quantity' => 2, 'status' => Order::STATUS_PENDING, 'queue_number' => 1003],
            ['email' => 'ana.jovanovic@example.com', 'event' => 'Partizan - Budućnost', 'ticket' => 'Tribina', 'quantity' => 4, 'status' => Order::STATUS_QUEUED, 'queue_number' => 1004],
            ['email' => 'marko.petrovic@example.com', 'event' => 'Labudovo jezero', 'ticket' => 'Balkon', 'quantity' => 2, 'status' => Order::STATUS_PROCESSING, 'queue_number' => 1005],
            ['email' => 'jovana.ilic@example.com', 'event' => 'Belgrade Music Festival', 'ticket' => 'VIP', 'quantity' => 2, 'status' => Order::STATUS_CANCELLED, 'queue_number' => 1006],
        ];

        foreach ($orders as $orderData) {
            $user = User::query()->where('email', $orderData['email'])->first();
            $ticketType = TicketType::query()
                ->where('name', $orderData['ticket'])
                ->whereHas('event', function ($query) use ($orderData): void {
                    $query->where('title', $orderData['event']);
                })
                ->first();

            if (! $user || ! $ticketType) {
                continue;
            }

            $quantity = $orderData['quantity'];
            $unitPrice = (float) $ticketType->price;

            Order::query()->updateOrCreate(
                ['queue_number' => $orderData['queue_number']],
                [
                    'user_id' => $user->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => round($unitPrice * $quantity, 2),
                    'status' => $orderData['status'],
                    'purchased_at' => $orderData['status'] === Order::STATUS_PAID
                        ? now()->subDays(fake()->numberBetween(1, 14))
                        : null,
                ]
            );
        }

        $users = User::query()
            ->where('role', User::ROLE_USER)
            ->get();
        $ticketTypes = TicketType::query()->get();
        $nextQueueNumber = ((int) Order::query()->max('queue_number')) + 1;

        foreach (range(0, 14) as $index) {
            $ticketType = $ticketTypes->random();
            $quantity = fake()->numberBetween(1, min(4, $ticketType->max_per_order));
            $unitPrice = (float) $ticketType->price;
            $status = fake()->randomElement(Order::STATUSES);

            Order::factory()->create([
                'user_id' => $users->random()->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => round($unitPrice * $quantity, 2),
                'status' => $status,
                'queue_number' => $nextQueueNumber + $index,
                'purchased_at' => $status === Order::STATUS_PAID
                    ? now()->subDays(fake()->numberBetween(1, 60))
                    : null,
            ]);
        }
    }
}
