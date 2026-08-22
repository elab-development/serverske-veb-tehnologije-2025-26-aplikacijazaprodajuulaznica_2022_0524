<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            [
                'title' => 'Belgrade Music Festival',
                'description' => 'Veče regionalnih pop i rok izvođača na otvorenom.',
                'location' => 'Kalemegdan, Beograd',
                'starts_at' => now()->addWeeks(5)->setTime(20, 0),
                'ends_at' => now()->addWeeks(5)->setTime(23, 30),
            ],
            [
                'title' => 'Laravel Serbia Conference',
                'description' => 'Celodnevna konferencija posvećena Laravelu, PHP-u i razvoju modernih API aplikacija.',
                'location' => 'Sava Centar, Beograd',
                'starts_at' => now()->addWeeks(7)->setTime(9, 0),
                'ends_at' => now()->addWeeks(7)->setTime(18, 0),
            ],
            [
                'title' => 'Stand-up Night Novi Sad',
                'description' => 'Komedija uživo sa poznatim domaćim stand-up izvođačima.',
                'location' => 'Kulturna stanica Eđšeg, Novi Sad',
                'starts_at' => now()->addWeeks(3)->setTime(21, 0),
                'ends_at' => now()->addWeeks(3)->setTime(23, 0),
            ],
            [
                'title' => 'Partizan - Budućnost',
                'description' => 'Regionalni košarkaški derbi u Beogradskoj areni.',
                'location' => 'Beogradska arena, Beograd',
                'starts_at' => now()->addWeeks(4)->setTime(19, 30),
                'ends_at' => now()->addWeeks(4)->setTime(22, 0),
            ],
            [
                'title' => 'Labudovo jezero',
                'description' => 'Klasični balet Petra Iljiča Čajkovskog u izvođenju ansambla Narodnog pozorišta.',
                'location' => 'Narodno pozorište, Beograd',
                'starts_at' => now()->addWeeks(6)->setTime(19, 0),
                'ends_at' => now()->addWeeks(6)->setTime(21, 30),
            ],
        ];

        foreach ($events as $event) {
            Event::query()->updateOrCreate(
                ['title' => $event['title']],
                $event
            );
        }

        Event::factory()
            ->count(8)
            ->create();
    }
}
