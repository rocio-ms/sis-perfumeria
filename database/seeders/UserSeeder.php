<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $administrador = Role::where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $vendedor = Role::where('name', 'Vendedor')
            ->where('guard_name', 'web')
            ->firstOrFail();

        // ==============================
        // ADMINISTRADOR
        // ==============================

        $admin = User::updateOrCreate(
            [
                'username' => 'admin',
            ],
            [
                'name' => 'Administrador',
                'dni' => '12345678',
                'username' => 'admin',
                'email' => 'admin@belleza.com',
                'password' => Hash::make('12345678'),
                'activo' => true,
            ]
        );

         $admin->syncRoles([$administrador]);

         //Vendedor

          $vendedorUsuario = User::updateOrCreate(
            [
                'username' => 'vendedor',
            ],
            [
                'name' => 'Vendedor',
                'dni' => '87654321',
                'username' => 'vendedor',
                'email' => 'vendedor@belleza.com',
                'password' => Hash::make('12345678'),
                'activo' => true,
            ]
        );

        $vendedorUsuario->syncRoles([$vendedor]);
    }
}
