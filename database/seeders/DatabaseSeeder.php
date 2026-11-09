<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Buat role terlebih dahulu
        $this->call([
            RoleSeeder::class,
        ]);

        // 2. Buat akun administrator
        $this->call([
            AdminSeeder::class,
        ]);

        // 3. Buat akun testing jika belum tersedia
        // Hindari duplikasi ketika seeder dijalankan ulang.
        \App\Models\User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );
    }
}
