<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator',
                'email' => 'admin@billetterie.test',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Marko Petrović',
                'email' => 'marko.petrovic@example.com',
                'role' => User::ROLE_USER,
            ],
            [
                'name' => 'Jovana Ilić',
                'email' => 'jovana.ilic@example.com',
                'role' => User::ROLE_USER,
            ],
            [
                'name' => 'Nikola Savić',
                'email' => 'nikola.savic@example.com',
                'role' => User::ROLE_USER,
            ],
            [
                'name' => 'Ana Jovanović',
                'email' => 'ana.jovanovic@example.com',
                'role' => User::ROLE_USER,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                    'email_verified_at' => now(),
                ]
            );
        }

        User::factory()
            ->count(10)
            ->create();
    }
}
