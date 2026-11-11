<?php

namespace Tests\Feature;

use App\Models\Entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EntradaRegistroTest extends TestCase
{
    use RefreshDatabase;

    private int $empresaId;
    private int $categoriaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-09 12:00:00'));
        $usuarioId = DB::table('users')->insertGetId([
            'name' => 'Usuario de inventario',
            'dni' => '12345678',
            'username' => 'inventario-prueba',
            'email' => 'inventario@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $usuario = User::findOrFail($usuarioId);
        $usuario->givePermissionTo(Permission::findOrCreate('entradas.editar', 'web'));
        $this->actingAs($usuario);

        $tiendaId = DB::table('tiendas')->insertGetId(['nombre_comercial' => 'Tienda de prueba']);
        $this->empresaId = DB::table('empresas')->insertGetId([
            'tienda_id' => $tiendaId,
            'ruc' => '20111111111',
            'razon_social' => 'Empresa de prueba',
        ]);
        $this->categoriaId = DB::table('categorias')->insertGetId(['nombre' => 'Categoría de prueba']);
    }

    public function test_registra_varios_productos_con_sus_lotes_y_movimientos(): void
    {
        $primerDetalle = $this->crearDetalle('P-1');
        $segundoDetalle = $this->crearDetalle('P-2');
        DB::table('tipo_movimientos')->insert(['nombre' => 'Entrada', 'tipo' => 'entrada']);

        $this->post(route('entradas.store'), [
            'fecha_ingreso' => '2026-11-09',
            'detalles' => [
                [
                    ...$primerDetalle,
                    'codigo_barras_lote' => '01234567890',
                    'tiene_vencimiento' => true,
                    'fecha_vencimiento' => '2026-11-01',
                ],
                $segundoDetalle,
            ],
        ])->assertRedirect(route('entradas.index'))->assertSessionHasNoErrors();

        $entrada = Entrada::with('detalles.lotes')->sole();
        $this->assertSame('40.00', $entrada->total_compra);
        $this->assertCount(2, $entrada->detalles);
        $detalles = $entrada->detalles->keyBy('producto_id');

        $primerLote = $detalles[$primerDetalle['producto_id']]->lotes->sole();
        $this->assertSame('2026-11-30', $primerLote->fecha_vencimiento->toDateString());
        $this->assertSame('01234567890', $primerLote->codigo_barras);

        $segundoLote = $detalles[$segundoDetalle['producto_id']]->lotes->sole();
        $this->assertNull($segundoLote->fecha_vencimiento);
        $this->assertNotSame($primerLote->numero_lote, $segundoLote->numero_lote);

        foreach ([$primerDetalle, $segundoDetalle] as $detalle) {
            $this->assertDatabaseHas('productos', [
                'id' => $detalle['producto_id'],
                'stock_actual' => 2,
            ]);
            $this->assertDatabaseHas('kardexs', [
                'entrada_id' => $entrada->id,
                'producto_id' => $detalle['producto_id'],
                'lote_id' => $detalles[$detalle['producto_id']]->lotes->sole()->id,
                'cantidad' => 2,
            ]);
        }
    }

    public function test_el_buscador_devuelve_productos_activos_sin_navegar_al_listado(): void
    {
        $perfume = $this->crearDetalle('PRUEBA-ENT-10-01');
        $inactivo = $this->crearDetalle('PRUEBA-ENT-10-02');
        DB::table('productos')->where('id', $inactivo['producto_id'])->update(['activo' => false]);

        $this->getJson(route('entradas.buscarProductos', ['q' => 'PRUEBA-ENT-10']))
            ->assertOk()
            ->assertJsonCount(1, 'productos')
            ->assertJsonPath('productos.0.id', $perfume['producto_id'])
            ->assertJsonPath('productos.0.categoria.id', $this->categoriaId);

        $this->getJson(route('entradas.buscarProductos', ['q' => 'prueS']))
            ->assertOk()
            ->assertExactJson(['productos' => [], 'siguiente_cursor' => null]);

        $this->getJson(route('entradas.buscarProductos', ['q' => ['prueba']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_se_puede_recorrer_el_catalogo_y_registrar_mas_de_veinte_productos(): void
    {
        $detalles = [];
        for ($indice = 1; $indice <= 25; $indice++) {
            $detalles[] = $this->crearDetalle("LIBRE-{$indice}");
        }
        $detalles[0]['cantidad'] = 125;

        $primera = $this->getJson(route('entradas.buscarProductos'))
            ->assertOk()
            ->assertJsonCount(20, 'productos');
        $cursor = $primera->json('siguiente_cursor');
        $this->assertNotEmpty($cursor);

        $segunda = $this->getJson(route('entradas.buscarProductos', ['cursor' => $cursor]))
            ->assertOk()
            ->assertJsonCount(5, 'productos')
            ->assertJsonPath('siguiente_cursor', null);
        $ids = array_column(
            array_merge($primera->json('productos'), $segunda->json('productos')),
            'id'
        );
        $this->assertEqualsCanonicalizing(array_column($detalles, 'producto_id'), $ids);

        $this->getJson(route('entradas.buscarProductos', ['q' => 'IBRE-25']))
            ->assertOk()
            ->assertJsonPath('productos.0.id', $detalles[24]['producto_id']);

        DB::table('tipo_movimientos')->insert(['nombre' => 'Entrada', 'tipo' => 'entrada']);
        $this->post(route('entradas.store'), [
            'fecha_ingreso' => '2026-11-09',
            'detalles' => $detalles,
        ])->assertRedirect(route('entradas.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('detalle_entradas', 25);
        $this->assertDatabaseHas('detalle_entradas', [
            'producto_id' => $detalles[0]['producto_id'],
            'cantidad' => 125,
        ]);
    }

    public function test_las_fechas_invalidas_devuelven_errores_por_fila_sin_guardar(): void
    {
        $detalles = [
            [...$this->crearDetalle('P-1'), 'fecha_vencimiento' => 'sin-fecha'],
            [...$this->crearDetalle('P-2'), 'fecha_vencimiento' => '2026-10-01'],
            [...$this->crearDetalle('P-3'), 'tiene_vencimiento' => true],
        ];

        $this->from(route('entradas.index'))
            ->post(route('entradas.store'), [
                'fecha_ingreso' => '2026-02-30',
                'detalles' => $detalles,
            ])
            ->assertRedirect(route('entradas.index'))
            ->assertSessionHasErrors([
                'fecha_ingreso' => 'La fecha de ingreso no es válida.',
                'detalles.0.fecha_vencimiento' => 'La fecha de vencimiento no es válida.',
                'detalles.1.fecha_vencimiento' => 'La fecha de vencimiento debe ser posterior a hoy.',
                'detalles.2.fecha_vencimiento' => 'Selecciona el mes de vencimiento.',
            ]);

        $this->assertDatabaseEmpty('entradas');
        $this->assertDatabaseEmpty('detalle_entradas');
        $this->assertDatabaseEmpty('lotes');
        $this->assertDatabaseEmpty('kardexs');
    }

    public function test_la_edicion_valida_las_fechas_antes_de_modificar_la_entrada(): void
    {
        $entrada = Entrada::create([
            'codigo_ingreso' => 'ING-000001',
            'fecha_ingreso' => '2026-11-09',
            'total_compra' => 0,
            'user_id' => auth()->id(),
        ]);

        $this->from(route('entradas.index'))
            ->put(route('entradas.update', $entrada), [
                'tipo_comprobante' => 'factura',
                'fecha_ingreso' => 'sin-fecha',
                'detalles' => [
                    [...$this->crearDetalle('P-1'), 'fecha_vencimiento' => ['incorrecta']],
                ],
            ])
            ->assertRedirect(route('entradas.index'))
            ->assertSessionHasErrors(['fecha_ingreso', 'detalles.0.fecha_vencimiento']);

        $this->assertNull($entrada->refresh()->tipo_comprobante);
        $this->assertDatabaseEmpty('detalle_entradas');
    }

    public function test_corrige_cabecera_sin_alterar_stock_lotes_o_kardex(): void
    {
        DB::table('tipo_movimientos')->insert(['nombre' => 'Entrada', 'tipo' => 'entrada']);
        $detalle = $this->crearDetalle('P-CABECERA');
        $this->post(route('entradas.store'), [
            'fecha_ingreso' => '2026-11-09',
            'detalles' => [$detalle],
        ])->assertSessionHasNoErrors();

        $entrada = Entrada::with('detalles.lotes', 'kardexs')->sole();
        $lote = $entrada->detalles->sole()->lotes->sole();
        $kardex = $entrada->kardexs->sole();

        $this->put(route('entradas.update', $entrada), [
            'tipo_comprobante' => null,
            'observacion' => 'Cabecera corregida',
        ])->assertRedirect(route('entradas.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entradas', ['id' => $entrada->id, 'observacion' => 'Cabecera corregida']);
        $this->assertDatabaseHas('lotes', ['id' => $lote->id, 'cantidad_actual' => $detalle['cantidad']]);
        $this->assertDatabaseHas('productos', ['id' => $detalle['producto_id'], 'stock_actual' => $detalle['cantidad']]);
        $this->assertDatabaseHas('kardexs', ['id' => $kardex->id, 'cantidad' => $detalle['cantidad']]);
        $this->assertDatabaseCount('kardexs', 1);
    }

    private function crearDetalle(string $codigo): array
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => $codigo,
            'nombre' => "Producto {$codigo}",
            'unidad_medida' => 'unidad',
            'categoria_id' => $this->categoriaId,
            'empresa_id' => $this->empresaId,
        ]);

        return [
            'producto_id' => $productoId,
            'cantidad' => 2,
            'precio_compra' => 10,
            'precio_venta' => 15,
        ];
    }
}
