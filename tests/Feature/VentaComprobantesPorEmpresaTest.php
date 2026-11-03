<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VentaComprobantesPorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_creates_one_printable_receipt_per_brand_company(): void
    {
        $now = now();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Vendedor de prueba',
            'dni' => '12345678',
            'username' => 'vendedor-prueba',
            'email' => 'vendedor@example.test',
            'email_verified_at' => $now,
            'password' => Hash::make('password'),
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $user = User::findOrFail($userId);
        $user->givePermissionTo(
            Permission::create([
                'name' => 'ventas.crear',
                'guard_name' => 'web',
            ])
        );

        $tiendaId = DB::table('tiendas')->insertGetId([
            'nombre_comercial' => 'Tienda de prueba',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $empresas = collect(['Empresa A', 'Empresa B'])->mapWithKeys(
            fn($nombre, $index) => [
                $nombre => DB::table('empresas')->insertGetId([
                    'tienda_id' => $tiendaId,
                    'ruc' => $index === 0 ? '20111111111' : '20222222222',
                    'razon_social' => $nombre,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            ]
        );
        $tipoComprobanteId = DB::table('tipo_comprobantes')->insertGetId([
            'nombre' => 'Boleta',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $tipoPagoId = DB::table('tipo_pagos')->insertGetId([
            'nombre' => 'Efectivo',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $cajaId = DB::table('cajas')->insertGetId([
            'nombre' => 'Caja de prueba',
            'turno' => 'mañana',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sesionCajaId = DB::table('sesion_cajas')->insertGetId([
            'fecha_apertura' => $now,
            'monto_apertura' => 0,
            'estado' => 'abierta',
            'caja_id' => $cajaId,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Categoría de prueba',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $entradaId = DB::table('entradas')->insertGetId([
            'codigo_ingreso' => 'ENT-PRUEBA',
            'fecha_ingreso' => $now->toDateString(),
            'total_compra' => 20,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('tipo_movimientos')->insert([
            'nombre' => 'salida',
            'tipo' => 'salida',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $productos = [];

        foreach (['Empresa A', 'Empresa B'] as $index => $nombreEmpresa) {
            $empresaId = $empresas[$nombreEmpresa];
            $marcaId = DB::table('marcas')->insertGetId([
                'empresa_id' => $empresaId,
                'nombre' => "Marca {$nombreEmpresa}",
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $productoId = DB::table('productos')->insertGetId([
                'codigo' => 'P-'.($index + 1),
                'nombre' => "Producto {$nombreEmpresa}",
                'unidad_medida' => 'unidad',
                'stock_actual' => 1,
                'stock_minimo' => 0,
                'activo' => true,
                'categoria_id' => $categoriaId,
                'marca_id' => $marcaId,
                // Simula productos existentes cuyo empresa_id quedó desactualizado.
                'empresa_id' => $empresas['Empresa A'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $detalleEntradaId = DB::table('detalle_entradas')->insertGetId([
                'cantidad' => 1,
                'precio_compra' => 10,
                'precio_venta' => 15,
                'subtotal' => 10,
                'entrada_id' => $entradaId,
                'producto_id' => $productoId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $loteId = DB::table('lotes')->insertGetId([
                'numero_lote' => 'LOTE-'.($index + 1),
                'cantidad_inicial' => 1,
                'cantidad_actual' => 1,
                'detalle_entrada_id' => $detalleEntradaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($index === 0) {
                DB::table('serie_comprobantes')->insert([
                    'tienda_id' => $tiendaId,
                    'empresa_id' => $empresaId,
                    'tipo_comprobante_id' => $tipoComprobanteId,
                    'serie' => 'B001',
                    'correlativo_actual' => 0,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $productos[] = [
                'producto_id' => $productoId,
                'lote_id' => $loteId,
            ];
        }

        $datosVenta = [
            'sesion_caja_id' => $sesionCajaId,
            'tipo_comprobante_id' => $tipoComprobanteId,
            'fecha_emision' => $now->toDateTimeString(),
            'moneda' => 'soles',
            'forma_pago' => 'contado',
            'tipo_pago_id' => $tipoPagoId,
            'detalles' => array_map(
                fn($producto) => [
                    ...$producto,
                    'cantidad' => 1,
                    'unidad_medida' => 'unidad',
                    'precio_unitario' => 15,
                    'descripcion' => 'Producto de prueba',
                ],
                $productos
            ),
        ];

        $response = $this->actingAs($user)->post(
            route('ventas.store'),
            $datosVenta
        );

        $response->assertRedirect(route('ventas.create'));
        $response->assertSessionHas('comprobantes_emitidos_ids');

        $ventas = DB::table('ventas')->orderBy('empresa_emisora_id')->get();
        $this->assertCount(2, $ventas);
        $this->assertSame(
            $empresas->sort()->values()->all(),
            $ventas->pluck('empresa_emisora_id')->map(fn($id) => (int) $id)->all()
        );
        $this->assertSame(
            ['B001', 'B001'],
            $ventas->pluck('serie')->all()
        );
        $this->assertDatabaseHas('serie_comprobantes', [
            'tienda_id' => $tiendaId,
            'empresa_id' => $empresas['Empresa B'],
            'tipo_comprobante_id' => $tipoComprobanteId,
            'serie' => 'B001',
            'activo' => true,
            'correlativo_actual' => 1,
        ]);
        $this->assertCount(
            2,
            DB::table('detalle_ventas')->distinct('venta_id')->pluck('venta_id')
        );

        foreach ($ventas as $venta) {
            $this->assertSame(
                (int) $venta->empresa_emisora_id,
                (int) DB::table('detalle_ventas')
                    ->where('venta_id', $venta->id)
                    ->value('empresa_id')
            );
        }
    }
}
