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
           if ($admin->exists && $admin->role !== 'admin_kpap') {
              $this->command->error('Email ini sudah dipakai akun non-admin. Pakai email lain di ADMIN_EMAIL.');
              return;
   }
        $admin->name = 'Administrator';
        $admin->password = $password; // di-hash otomatis oleh cast 'hashed'
        $admin->role = 'admin_kpap';       // diisi langsung, bukan lewat fillable
        $admin->assignRole('admin_kpap'); // pastikan role admin_kpap sudah ada di tabel roles
        $admin->save();
        $this->command->info('Akun admin siap: ' . $admin->email);
    }
}
