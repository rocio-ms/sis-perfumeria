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

    public function test_sale_creates_receipts_per_company_and_reconciles_cash_and_inventory(): void
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
            'monto_apertura' => 100,
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
            DB::table('precios_productos')->insert([
                'producto_id' => $productoId,
                'precio_venta' => 15,
                'activo' => true,
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

        $this->actingAs($user);
        $this->withoutVite()->get(route('ventas.create'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('productos', 2)
                ->where('categorias.0.stock_total', 2));

        $precioId = DB::table('precios_productos')->where('producto_id', $productos[0]['producto_id'])->value('id');
        DB::table('precios_productos')->where('id', $precioId)->update(['activo' => false]);
        $this->getJson(route('ventas.productos.buscar', ['search' => 'P-1']))
            ->assertOk()->assertJsonCount(0, 'productos');
        $this->postJson(route('ventas.store'), $datosVenta)
            ->assertUnprocessable()->assertJsonValidationErrors('detalles.precio');
        DB::table('precios_productos')->where('id', $precioId)->update(['activo' => true]);

        $ventaSinPrecio = $datosVenta;
        $ventaSinPrecio['detalles'][0]['precio_unitario'] = 0;
        $this->postJson(route('ventas.store'), $ventaSinPrecio)
            ->assertUnprocessable()->assertJsonValidationErrors('detalles.precio');

        $loteVencido = $productos[0]['lote_id'];
        DB::table('lotes')->where('id', $loteVencido)->update(['fecha_vencimiento' => $now->copy()->subDays(2)->toDateString()]);
        $this->getJson(route('ventas.productos.buscar', ['search' => 'P-1']))
            ->assertOk()->assertJsonCount(0, 'productos');
        $this->postJson(route('ventas.store'), $datosVenta)
            ->assertUnprocessable()->assertJsonValidationErrors('detalles.stock');
        DB::table('lotes')->where('id', $loteVencido)->update(['fecha_vencimiento' => null]);
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('kardexs', 0);

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

        foreach (['ingreso' => 20, 'egreso' => 10] as $tipo => $monto) {
            $this->postJson(route('movimientos-caja.store'), [
                'sesion_caja_id' => $sesionCajaId, 'tipo' => $tipo,
                'concepto' => 'Movimiento de prueba', 'monto' => $monto,
            ])->assertOk()->assertJsonPath('success', true);
        }

        $user->givePermissionTo(Permission::findOrCreate('sesiones-caja.cerrar', 'web'));
        $rutaCierre = route('sesiones-caja.cerrar', $sesionCajaId);
        $this->put($rutaCierre, ['monto_cierre_real' => 140])
            ->assertRedirect(route('sesiones-caja.index'))->assertSessionHasNoErrors();
        $this->putJson($rutaCierre, ['monto_cierre_real' => 145])
            ->assertUnprocessable()->assertJsonValidationErrors('caja');
        $this->assertDatabaseHas('sesion_cajas', [
            'id' => $sesionCajaId, 'estado' => 'cerrada',
            'monto_cierre_teorico' => 140, 'monto_cierre_real' => 140, 'diferencia' => 0,
        ]);
        $this->postJson(route('ventas.store'), $datosVenta)
            ->assertUnprocessable()->assertJsonValidationErrors('sesion_caja_id');
        $this->assertDatabaseCount('ventas', 2);
        $this->assertDatabaseCount('kardexs', 2);

        $producto = DB::table('productos')->find($productos[0]['producto_id']);
        $user->givePermissionTo(Permission::findOrCreate('productos.editar', 'web'));
        $this->put(route('productos.update', $producto->id), [
            'nombre' => 'Nombre corregido', 'unidad_medida' => 'unidad', 'stock_minimo' => 0,
            'stock_actual' => 1, 'volumen_ml' => 0, 'marca_id' => $producto->marca_id,
            'categoria_id' => $categoriaId,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'stock_actual' => 0]);

        DB::table('ventas')->update(['fecha_emision' => '2026-11-10 06:00:00']);
        DB::table('ventas')->where('id', $ventas->first()->id)->update(['fecha_emision' => '2026-11-10 02:00:00']);
        $this->withoutVite()->get(route('reportes.index', [
            'periodo_mas_vendidos' => 'dia', 'fecha_referencia_mas_vendidos' => '2026-11-09',
        ]))->assertOk()->assertInertia(fn ($page) => $page->has('productosMasVendidos', 1)
            ->where('productosMasVendidos.0.id', $producto->id));
    }
}
