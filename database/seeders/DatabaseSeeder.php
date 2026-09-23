<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seedUsers = [
            [
                'name' => 'Quản lý',
                'email' => 'admin@giatui.com',
                'password' => 'admin123',
            ],
            [
                'name' => 'Nhân viên',
                'email' => 'staff@giatui.com',
                'password' => 'staff123',
            ],
        ];

        foreach ($seedUsers as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                ]
            );
        }
    }
}
