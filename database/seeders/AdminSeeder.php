<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command->error('ADMIN_EMAIL dan ADMIN_PASSWORD belum diisi di .env');
            return;
        }

        $admin = User::firstOrNew(['email' => strtolower($email)]);
           if ($admin->exists && $admin->role !== 'admin') {
              $this->command->error('Email ini sudah dipakai akun non-admin. Pakai email lain di ADMIN_EMAIL.');
              return;
   }
        $admin->name = 'Administrator';
        $admin->password = $password; // di-hash otomatis oleh cast 'hashed'
        $admin->role = 'admin';       // diisi langsung, bukan lewat fillable
        $admin->save();
        $this->command->info('Akun admin siap: ' . $admin->email);
    }
}
