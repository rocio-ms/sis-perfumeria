<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductoImagenTest extends TestCase
{
    use RefreshDatabase;

    private int $empresaId;

    private array $datosProducto;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $usuarioId = DB::table('users')->insertGetId([
            'name' => 'Usuario de catálogo',
            'dni' => '12345678',
            'username' => 'catalogo-prueba',
            'password' => Hash::make('password'),
        ]);
        $usuario = User::findOrFail($usuarioId);
        $usuario->givePermissionTo(Permission::findOrCreate('productos.crear', 'web'));
        $usuario->givePermissionTo(Permission::findOrCreate('productos.editar', 'web'));
        $this->actingAs($usuario);

        $tiendaId = DB::table('tiendas')->insertGetId(['nombre_comercial' => 'Tienda de prueba']);
        $this->empresaId = DB::table('empresas')->insertGetId([
            'tienda_id' => $tiendaId,
            'ruc' => '20111111111',
            'razon_social' => 'Empresa de prueba',
        ]);
        $this->datosProducto = [
            'nombre' => 'Perfume de prueba',
            'unidad_medida' => 'frasco',
            'stock_minimo' => 0,
            'stock_actual' => 0,
            'volumen_ml' => 50,
            'marca_id' => DB::table('marcas')->insertGetId([
                'nombre' => 'Marca de prueba',
                'empresa_id' => $this->empresaId,
            ]),
            'categoria_id' => DB::table('categorias')->insertGetId(['nombre' => 'Perfumes']),
        ];
    }

    public function test_crear_producto_guarda_la_url_publica_y_el_archivo(): void
    {
        $this->from(route('productos.index'))
            ->post(route('productos.store'), [
                ...$this->datosProducto,
                'imagen_url' => $this->imagen(),
            ])
            ->assertRedirect(route('productos.index'))
            ->assertSessionHasNoErrors();

        $producto = Producto::sole();
        $this->comprobarImagen($producto);
    }

    public function test_editar_conserva_una_imagen_anterior_y_puede_reemplazarla(): void
    {
        $producto = $this->productoConImagenAnterior();
        $url = route('productos.update', $producto);
        $this->assertSame('/storage/productos/anterior.png', $producto->imagen_url);

        $this->post($url, [...$this->datosProducto, '_method' => 'PUT'])
            ->assertSessionHasNoErrors();
        $this->assertSame('productos/anterior.png', $producto->refresh()->getRawOriginal('imagen_url'));
        Storage::disk('public')->assertExists('productos/anterior.png');

        $this->post($url, [
            ...$this->datosProducto,
            '_method' => 'PUT',
            'imagen_url' => $this->imagen(),
        ])->assertSessionHasNoErrors();

        $this->comprobarImagen($producto->refresh());
        Storage::disk('public')->assertMissing('productos/anterior.png');
    }

    public function test_una_edicion_fallida_conserva_el_archivo_anterior(): void
    {
        $producto = $this->productoConImagenAnterior();

        $this->from(route('productos.index'))
            ->post(route('productos.update', $producto), [
                ...$this->datosProducto,
                '_method' => 'PUT',
                'marca_id' => null,
                'marca_nombre' => 'Marca nueva sin empresa',
                'imagen_url' => $this->imagen(),
            ])
            ->assertRedirect(route('productos.index'))
            ->assertSessionHasErrors('empresa_id');

        $this->assertSame('productos/anterior.png', $producto->refresh()->getRawOriginal('imagen_url'));
        $this->assertSame(['productos/anterior.png'], Storage::disk('public')->files('productos'));
    }

    public function test_busca_por_nombre_sin_tildes_ni_espacios(): void
    {
        auth()->user()->givePermissionTo(Permission::findOrCreate('productos.index', 'web'));
        $producto = $this->productoConImagenAnterior();
        $producto->update(['nombre' => 'Kit Perfumería  Rosa', 'codigo_barras' => '87126757391']);

        $this->withoutVite();
        foreach (['perfumeriarosa', 'PERFUMERÍA    ROSA', 'meria ro', '87126757391'] as $texto) {
            $this->get(route('productos.index', ['search' => $texto]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->has('productos.data', 1)
                    ->where('productos.data.0.id', $producto->id));
        }
    }

    private function productoConImagenAnterior(): Producto
    {
        Storage::disk('public')->put('productos/anterior.png', 'imagen anterior');

        return Producto::create([
            ...$this->datosProducto,
            'codigo' => '000100',
            'empresa_id' => $this->empresaId,
            'imagen_url' => 'productos/anterior.png',
        ]);
    }

    private function imagen(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('producto.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jH0kAAAAASUVORK5CYII='
        ));
    }

    private function comprobarImagen(Producto $producto): void
    {
        $url = $producto->getRawOriginal('imagen_url');
        $this->assertMatchesRegularExpression('#^/storage/productos/[a-zA-Z0-9]+\.png$#', $url);
        $this->assertSame($url, $producto->imagen_url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    }
}
