<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tienda;
use App\Models\TipoComprobante;
use App\Models\SerieComprobante;

class SerieComprobanteSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // 1. OBTENER LA TIENDA
        // ============================================================

        $tienda = Tienda::first();

        if (!$tienda) {
            $this->command->error(
                'No existe ninguna tienda registrada.'
            );

            return;
        }

        // ============================================================
        // 2. OBTENER TIPOS DE COMPROBANTE
        // ============================================================

        $boleta = TipoComprobante::where(
            'nombre',
            'Boleta'
        )->first();

        $factura = TipoComprobante::where(
            'nombre',
            'Factura'
        )->first();

        if (!$boleta) {
            $this->command->error(
                'No existe el tipo de comprobante "Boleta".'
            );

            return;
        }

        if (!$factura) {
            $this->command->error(
                'No existe el tipo de comprobante "Factura".'
            );

            return;
        }

        // ============================================================
        // 3. SERIE PARA BOLETAS
        // ============================================================

        SerieComprobante::updateOrCreate(
            [
                'tienda_id' => $tienda->id,
                'empresa_id' => null,
                'tipo_comprobante_id' => $boleta->id,
                'serie' => 'B001',
            ],
            [
                'correlativo_actual' => 0,
                'activo' => true,
            ]
        );

        // ============================================================
        // 4. SERIE PARA FACTURAS
        // ============================================================

        SerieComprobante::updateOrCreate(
            [
                'tienda_id' => $tienda->id,
                'empresa_id' => null,
                'tipo_comprobante_id' => $factura->id,
                'serie' => 'F001',
            ],
            [
                'correlativo_actual' => 0,
                'activo' => true,
            ]
        );

        // ============================================================
        // 5. MENSAJE FINAL
        // ============================================================

        $this->command->info(
            'Serie B001 configurada para Boletas.'
        );

        $this->command->info(
            'Serie F001 configurada para Facturas.'
        );

        $this->command->info(
            'Series de comprobantes configuradas correctamente.'
        );
    }
}