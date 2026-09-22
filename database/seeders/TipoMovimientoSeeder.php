<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoMovimientoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tipo_movimientos')->updateOrInsert(
            ['tipo' => 'entrada'],
            [
                'nombre' => 'Entrada',
                'updated_at' => now(),
            ]
        );

        DB::table('tipo_movimientos')->updateOrInsert(
            ['tipo' => 'salida'],
            [
                'nombre' => 'Salida',
                'updated_at' => now(),
            ]
        );
    }
}
