<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador',
                'dni' => '12345678',
                'email' => 'admin@belleza.com',
                'password' => Hash::make('admin123'),
                'activo' => true,
            ]
        );

         $this->call([
            TipoComprobanteSeeder::class,
            TipoPagoSeeder::class,
            TipoMovimientoSeeder::class,
            PermissionSeeder::class,
            // SerieComprobanteSeeder::class,
        ]);

        $adminUser = User::where('username', 'admin')->first();
        if ($adminUser) {
            $adminUser->syncRoles(['Administrador']);
        }
    }
}
