<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin_kpap', 'web');
        Role::findOrCreate('mahasiswa', 'web');

        // samakan akun yang sudah ada berdasarkan kolom users.role
        User::where('role', 'admin_kpap')->each(fn (User $user) => $user->assignRole('admin_kpap'));
        User::where('role', 'mahasiswa')->each(fn (User $user) => $user->assignRole('mahasiswa'));
    }
}