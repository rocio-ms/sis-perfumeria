<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EstadoRegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_activate_and_deactivate_each_supported_record(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Administrador', 'web'));
        $this->actingAs($admin);

        $tienda = DB::table('tiendas')->insertGetId(['nombre_comercial' => 'Tienda']);
        $empresa = DB::table('empresas')->insertGetId([
            'tienda_id' => $tienda, 'ruc' => '20123456789', 'razon_social' => 'Empresa de prueba',
        ]);
        $categoria = DB::table('categorias')->insertGetId(['nombre' => 'Perfumes']);
        $marca = DB::table('marcas')->insertGetId(['nombre' => 'Marca de prueba', 'empresa_id' => $empresa]);
        $subcategoria = DB::table('subcategorias')->insertGetId([
            'nombre' => 'Fragancias', 'categoria_id' => $categoria,
        ]);
        $caja = DB::table('cajas')->insertGetId(['nombre' => 'Caja de prueba', 'turno' => 'mañana']);
        $cliente = DB::table('clientes')->insertGetId([
            'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'razon_social_nombre' => 'Cliente de prueba', 'nombres' => 'Cliente', 'ap_paterno' => 'de', 'ap_materno' => 'prueba',
        ]);
        $proveedor = DB::table('proveedors')->insertGetId([
            'razon_social' => 'Proveedor de prueba', 'nombre_comercial' => 'Proveedor de prueba', 'tipo_documento' => 'ruc',
            'n_documento' => '20234567891', 'nombre_contacto' => 'Contacto de prueba',
        ]);
        $producto = DB::table('productos')->insertGetId([
            'codigo' => 'P-1', 'nombre' => 'Producto de prueba', 'unidad_medida' => 'unidad',
            'categoria_id' => $categoria, 'marca_id' => $marca, 'empresa_id' => $empresa,
        ]);
        $usuario = User::factory()->create();

        foreach ([
            ['cajas', 'cajas', $caja],
            ['categorias', 'categorias', $categoria],
            ['clientes', 'clientes', $cliente],
            ['empresas', 'empresas', $empresa],
            ['marcas', 'marcas', $marca],
            ['productos', 'productos', $producto],
            ['proveedores', 'proveedors', $proveedor],
            ['subcategorias', 'subcategorias', $subcategoria],
            ['usuarios', 'users', $usuario->id],
        ] as [$modulo, $tabla, $id]) {
            $url = route('registros.estado', ['modulo' => $modulo, 'registro' => $id]);
            $lista = url("/{$modulo}");

            foreach ([false, true] as $activo) {
                $this->from($lista)->patch($url, ['activo' => $activo])
                    ->assertStatus(303)->assertRedirect($lista);
                $this->assertDatabaseHas($tabla, ['id' => $id, 'activo' => $activo]);
            }
        }
    }

    public function test_status_requires_edit_permission_and_is_preserved_by_an_ordinary_edit(): void
    {
        $usuario = User::factory()->create();
        $caja = DB::table('cajas')->insertGetId(['nombre' => 'Caja de prueba', 'turno' => 'mañana']);
        $url = route('registros.estado', ['modulo' => 'cajas', 'registro' => $caja]);
        $this->actingAs($usuario)->patchJson($url, ['activo' => false])->assertForbidden();

        $usuario->givePermissionTo(Permission::findOrCreate('cajas.editar', 'web'));
        $this->patchJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('activo');
        $this->from(route('cajas.index', ['search' => 'Caja']))
            ->patch($url, ['activo' => false, 'nombre' => 'Ignorado'])
            ->assertStatus(303)->assertRedirect(route('cajas.index', ['search' => 'Caja']));
        $this->assertDatabaseHas('cajas', ['id' => $caja, 'nombre' => 'Caja de prueba', 'activo' => false]);

        $this->put(route('cajas.update', $caja), ['nombre' => 'Caja editada', 'turno' => 'tarde'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cajas', ['id' => $caja, 'nombre' => 'Caja editada', 'activo' => false]);
        $this->patchJson('/ventas/1/estado', ['activo' => false])->assertNotFound();
    }
}
