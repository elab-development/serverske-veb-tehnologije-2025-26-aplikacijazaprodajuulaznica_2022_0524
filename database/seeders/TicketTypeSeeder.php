<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Seeder;

class TicketTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ticketTypes = [
            'Belgrade Music Festival' => [
                ['name' => 'Regular', 'description' => 'Standardni ulaz na festivalski prostor.', 'price' => 3500, 'quantity_total' => 800, 'quantity_available' => 620, 'max_per_order' => 6],
                ['name' => 'VIP', 'description' => 'Poseban ulaz, VIP zona i uključeno piće.', 'price' => 9000, 'quantity_total' => 120, 'quantity_available' => 75, 'max_per_order' => 4],
                ['name' => 'Backstage', 'description' => 'VIP pogodnosti i pristup backstage prostoru.', 'price' => 15000, 'quantity_total' => 30, 'quantity_available' => 12, 'max_per_order' => 2],
            ],
            'Laravel Serbia Conference' => [
                ['name' => 'Standard', 'description' => 'Pristup svim predavanjima i konferencijskom materijalu.', 'price' => 12000, 'quantity_total' => 250, 'quantity_available' => 180, 'max_per_order' => 5],
                ['name' => 'Student', 'description' => 'Studentska karta uz obaveznu studentsku legitimaciju.', 'price' => 6000, 'quantity_total' => 80, 'quantity_available' => 55, 'max_per_order' => 2],
                ['name' => 'Workshop Pass', 'description' => 'Konferencija i praktična radionica sa predavačima.', 'price' => 18000, 'quantity_total' => 40, 'quantity_available' => 22, 'max_per_order' => 2],
            ],
            'Stand-up Night Novi Sad' => [
                ['name' => 'General Admission', 'description' => 'Slobodan izbor mesta u standardnoj zoni.', 'price' => 1800, 'quantity_total' => 180, 'quantity_available' => 96, 'max_per_order' => 6],
                ['name' => 'Front Row', 'description' => 'Rezervisano mesto u prva dva reda.', 'price' => 3200, 'quantity_total' => 25, 'quantity_available' => 8, 'max_per_order' => 4],
            ],
            'Partizan - Budućnost' => [
                ['name' => 'Tribina', 'description' => 'Numerisano mesto na tribini.', 'price' => 2500, 'quantity_total' => 1200, 'quantity_available' => 830, 'max_per_order' => 6],
                ['name' => 'Parter', 'description' => 'Mesto u parteru uz teren.', 'price' => 4500, 'quantity_total' => 350, 'quantity_available' => 190, 'max_per_order' => 4],
                ['name' => 'VIP loža', 'description' => 'Mesto u VIP loži sa keteringom.', 'price' => 12000, 'quantity_total' => 40, 'quantity_available' => 18, 'max_per_order' => 2],
            ],
            'Labudovo jezero' => [
                ['name' => 'Parter', 'description' => 'Numerisano mesto u parteru pozorišta.', 'price' => 4200, 'quantity_total' => 220, 'quantity_available' => 145, 'max_per_order' => 6],
                ['name' => 'Balkon', 'description' => 'Numerisano mesto na balkonu.', 'price' => 2800, 'quantity_total' => 140, 'quantity_available' => 87, 'max_per_order' => 6],
            ],
        ];

        foreach ($ticketTypes as $eventTitle => $types) {
            $event = Event::query()->where('title', $eventTitle)->first();

            if (! $event) {
                continue;
            }

            foreach ($types as $type) {
                TicketType::query()->updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'name' => $type['name'],
                    ],
                    $type
                );
            }
        }

        Event::query()
            ->inRandomOrder()
            ->limit(5)
            ->get()
            ->each(function (Event $event): void {
                $nextTypeNumber = $event->ticketTypes()->count() + 1;

                TicketType::factory()
                    ->count(2)
                    ->for($event)
                    ->sequence(
                        ['name' => "Generated Ticket Type {$nextTypeNumber}"],
                        ['name' => 'Generated Ticket Type '.($nextTypeNumber + 1)]
                    )
                    ->create();
            });
    }
}
