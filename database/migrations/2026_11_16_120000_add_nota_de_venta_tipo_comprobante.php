<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tipo_comprobantes')->insertOrIgnore([
            'nombre' => 'Nota de venta',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('tipo_comprobantes')
            ->where('nombre', 'Nota de venta')
            ->whereNotIn('id', DB::table('ventas')->select('tipo_comprobante_id'))
            ->whereNotIn('id', DB::table('serie_comprobantes')->select('tipo_comprobante_id'))
            ->delete();
    }
};
