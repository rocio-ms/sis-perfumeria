<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TipoPago;

class TipoPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            'Efectivo',
            'Tarjeta',
            'Yape / Plin',
        ];

        foreach ($tipos as $nombre) {
            TipoPago::updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
