<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TipoComprobante;

class TipoComprobanteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            'Boleta',
            'Factura',
            'Guía de Remisión',
        ];

        foreach ($tipos as $nombre) {
            TipoComprobante::updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
