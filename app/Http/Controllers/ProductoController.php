<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\PrecioProducto;
use App\Models\Empresa;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    public function index()
    {
        $productos = Producto::with([
            'categoria',
            'marca',
            'empresa',
            'precioActual'
        ])
            ->orderBy('id', 'desc')
            ->paginate(10);

        $categorias = Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $marcas = Marca::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $empresas = Empresa::where('activo', true)
            ->orderBy('nombre_comercial')
            ->get();

        return Inertia::render('Productos/Index', [
            'productos' => $productos,
            'categorias' => $categorias,
            'marcas' => $marcas,
            'empresas' => $empresas,
            'siguienteCodigo' => $this->generarCodigoInterno(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Productos/Form', [
            'codigoInicial' => $this->generarCodigoInterno(),
            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'marcas' => Marca::where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'empresas' => Empresa::where('activo', true)
                ->orderBy('nombre_comercial')
                ->get(),
        ]);
    }

    private function generarCodigoInterno()
    {
        $ultimoCodigo = Producto::orderByRaw('CAST(codigo AS UNSIGNED) DESC')
            ->value('codigo');

        if (!$ultimoCodigo) {
            return '000100';
        }

        $siguiente = (int) $ultimoCodigo + 1;

        return str_pad($siguiente, 6, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    | Aquí NO registramos precio.
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_barras' => 'nullable|string|max:50|unique:productos,codigo_barras',

            'nombre' => 'required|string|max:150',

            'descripcion' => 'nullable|string',

            'unidad_medida' =>
            'required|in:und,caja,pqt,blister,frasco,botella,ml,l,gr,kg,kit,par',

            'stock_minimo' => 'required|integer|min:0',

            'stock_actual' => 'required|integer|min:0',

            'volumen_ml' => 'required|numeric|min:0',

            'imagen_url' =>
            'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',

            'activo' => 'boolean',

            'marca_id' => 'required|exists:marcas,id',

            'categoria_id' => 'required|exists:categorias,id',
        ]);

        //Obtener la empresa de la marca
        $marca = Marca::find($validated['marca_id']);

        $validated['empresa_id'] = $marca->empresa_id;

        //Generar código

        $validated['codigo'] = $this->generarCodigoInterno();

        //Imagen

        if ($request->hasFile('imagen_url')) {
            $validated['imagen_url'] = $request
                ->file('imagen_url')
                ->store('productos', 'public');
        }

        Producto::create($validated);

        return back()->with(
            'success',
            'Producto creado correctamente.'
        );
    }



    public function show(Producto $producto)
    {
        $producto->load([
            'categoria',
            'marca',
            'empresa',
            'precioActual'
        ]);

        return Inertia::render('Productos/Show', [
            'producto' => $producto,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(Producto $producto)
    {
        $producto->load([
            'categoria',
            'marca',
            'empresa',
            'precioActual'
        ]);

        return Inertia::render('Productos/Form', [
            'producto' => $producto,

            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'marcas' => Marca::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'empresas' => Empresa::where('activo', true)
                ->orderBy('nombre_comercial')
                ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Producto $producto)
    {


        $validated = $request->validate([

            'codigo_barras' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('productos', 'codigo_barras')
                    ->ignore($producto->id),
            ],

            'nombre' => 'required|string|max:150',

            'descripcion' => 'nullable|string',

            'unidad_medida' =>
            'required|in:und,caja,pqt,blister,frasco,botella,ml,l,gr,kg,kit,par',

            'stock_minimo' => 'required|integer|min:0',

            'stock_actual' => 'required|integer|min:0',

            'volumen_ml' => 'required|numeric|min:0',

            'imagen_url' =>
            'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',

            'activo' => 'boolean',

            'marca_id' => 'required|exists:marcas,id',

            'categoria_id' => 'required|exists:categorias,id',

            'tiene_descuento' => 'boolean',

            'descuento' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | PRECIO
            |--------------------------------------------------------------------------
            */

            'precio_venta' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
        ]);

        DB::transaction(function () use (
            $request,
            $validated,
            $producto
        ) {

            $marca = Marca::findOrFail($validated['marca_id']);

            $empresaId = $marca->empresa_id;

            /*
            |--------------------------------------------------------------------------
            | IMAGEN
            |--------------------------------------------------------------------------
            */

            $imagen = $producto->imagen_url;

            if ($request->hasFile('imagen_url')) {

                if ($producto->imagen_url) {
                    Storage::disk('public')->delete(
                        $producto->imagen_url
                    );
                }

                $imagen = $request->file('imagen_url')->store('productos', 'public');
            } else {

                $validated['imagen_url'] =
                    $producto->imagen_url;
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR PRODUCTO
            |--------------------------------------------------------------------------
            */

            $producto->update([
                'codigo_barras' => $validated['codigo_barras'] ?? null,
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'unidad_medida' => $validated['unidad_medida'],
                'stock_minimo' => $validated['stock_minimo'],
                'stock_actual' => $validated['stock_actual'],
                'volumen_ml' => $validated['volumen_ml'],
                'imagen_url' => $validated['imagen_url'],
                'activo' => $validated['activo'] ?? true,
                'marca_id' => $validated['marca_id'],
                'categoria_id' => $validated['categoria_id'],
                'empresa_id' => $empresaId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR PRECIO
            |--------------------------------------------------------------------------
            */

            if (
                array_key_exists('precio_venta', $validated) &&
                $validated['precio_venta'] !== null
            ) {

                $producto->refresh();
                $precioActual = $producto->precioActual;

                $nuevoPrecio = (float) $validated['precio_venta'];

                $nuevoTieneDescuento = (bool) (
                    $validated['tiene_descuento'] ?? false
                );

                $nuevoDescuento = $nuevoTieneDescuento
                    ? (float) ($validated['descuento'] ?? 0)
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Solo crear nuevo precio si realmente cambió
                |--------------------------------------------------------------------------
                */

                $precioCambio = !$precioActual 
                || (float) $precioActual->precio_venta !== $nuevoPrecio
                || (bool) $precioActual->tiene_descuento !== $nuevoTieneDescuento
                || (float) $precioActual->descuento !== $nuevoDescuento;

                if ($precioCambio) {

                    /*
                    |--------------------------------------------------------------------------
                    | Desactivar precio anterior
                    |--------------------------------------------------------------------------
                    */

                    PrecioProducto::where(
                        'producto_id',
                        $producto->id
                    )
                        ->where('activo', true)
                        ->update([
                            'activo' => false
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Crear nuevo precio
                    |--------------------------------------------------------------------------
                    */

                    PrecioProducto::create([
                        'producto_id' => $producto->id,
                        'precio_venta' => $validated['precio_venta'],
                        'tiene_descuento' => $validated['tiene_descuento'] ?? false,
                        'descuento' => $validated['descuento'] ?? 0,
                        'activo' => true,
                    ]);
                }
            }
        });

        return back()->with(
            'success',
            'Producto actualizado correctamente.'
        );
    }
}
