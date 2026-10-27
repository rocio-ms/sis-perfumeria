<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\PrecioProducto;
use App\Models\Empresa;
use App\Models\Subcategoria;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    private const UNIDADES_MEDIDA = [
        'unidad',
        'pieza',
        'juego',
        'set',
        'par',
        'coleccion',
        'paquete',
        'ml',
        'l',
        'tubo',
        'equipo',
        'docena',
        'frasco',
        'bolsita',
        'gr',
        'kit',
        'cajita',
    ];

    public function resolverImportacion(Request $request)
    {
        $validated = $request->validate([
            'codigos' => ['required', 'array', 'min:1', 'max:500'],
            'codigos.*' => ['required', 'string', 'max:100'],
            'incluir_lotes' => ['sometimes', 'boolean'],
        ]);

        $codigos = collect($validated['codigos'])
            ->map(fn ($codigo) => trim($codigo))
            ->filter()
            ->unique()
            ->values();
        $codigosNumericos = $codigos
            ->filter(fn ($codigo) => ctype_digit($codigo))
            ->map(fn ($codigo) => ltrim($codigo, '0') ?: '0')
            ->unique()
            ->values();

        $consulta = Producto::query()
            ->where('activo', true)
            ->where(function ($query) use ($codigos, $codigosNumericos) {
                $query->whereIn('codigo', $codigos)
                    ->orWhereIn('codigo_barras', $codigos);
                if ($codigosNumericos->isNotEmpty()) {
                    $query->orWhereIn('codigo', $codigosNumericos);
                }
            });

        if ($request->boolean('incluir_lotes')) {
            $consulta->with([
                'lotes' => function ($query) {
                    $query->where('cantidad_actual', '>', 0)
                        ->with('detalleEntrada')
                        ->orderBy('id');
                },
            ]);
        }

        $productos = $consulta
            ->get([
                'id',
                'codigo',
                'codigo_barras',
                'nombre',
                'unidad_medida',
                'stock_actual',
            ]);

        return response()->json([
            'productos' => $productos,
        ]);
    }

    public function index()
    {
        $productos = Producto::with([
            'categoria',
            'subcategoria',
            'marca',
            'empresa',
            'precioActual'
        ])
            ->orderBy('id', 'desc')
            ->get();

        $categorias = Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $subcategorias = Subcategoria::with('categoria')
            ->where('activo', true)
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
            'subcategorias' => $subcategorias,
            'ubicaciones' => Producto::UBICACIONES,
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
            'subcategorias' => Subcategoria::with('categoria')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'ubicaciones' => Producto::UBICACIONES,
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

            'ubicacion' => ['nullable', Rule::in(Producto::UBICACIONES)],

            'unidad_medida' => ['required', Rule::in(self::UNIDADES_MEDIDA)],

            'stock_minimo' => 'required|integer|min:0',

            'stock_actual' => 'required|integer|min:0',

            'volumen_ml' => 'required|numeric|min:0',

            'imagen_url' =>
            'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',

            'activo' => 'boolean',

            'marca_id' => 'required|exists:marcas,id',

            'categoria_id' => 'required|exists:categorias,id',

            'subcategoria_id' => [
                'nullable',
                Rule::exists('subcategorias', 'id')->where('categoria_id', $request->categoria_id),
            ],
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
            'subcategoria',
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
            'subcategoria',
            'marca',
            'empresa',
            'precioActual'
        ]);

        return Inertia::render('Productos/Form', [
            'producto' => $producto,

            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'subcategorias' => Subcategoria::with('categoria')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'ubicaciones' => Producto::UBICACIONES,
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
        $reglasCodigoBarras = [
            'nullable',
            'string',
            'max:50',
        ];

        if ($request->input('codigo_barras') !== $producto->codigo_barras) {
            $reglasCodigoBarras[] = Rule::unique('productos', 'codigo_barras')
                ->ignore($producto->getKey(), $producto->getKeyName());
        }

        $validated = $request->validate([
            'codigo_barras' => $reglasCodigoBarras,

            'nombre' => 'required|string|max:150',

            'descripcion' => 'nullable|string',

            'ubicacion' => ['nullable', Rule::in(Producto::UBICACIONES)],

            'unidad_medida' => ['required', Rule::in(self::UNIDADES_MEDIDA)],

            'stock_minimo' => 'required|integer|min:0',

            'stock_actual' => 'required|integer|min:0',

            'volumen_ml' => 'required|numeric|min:0',

            'imagen_url' =>
            'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',

            'activo' => 'boolean',

            'marca_id' => 'required|exists:marcas,id',

            'categoria_id' => 'required|exists:categorias,id',

            'subcategoria_id' => [
                'nullable',
                Rule::exists('subcategorias', 'id')->where('categoria_id', $request->categoria_id),
            ],

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
                'ubicacion' => $validated['ubicacion'] ?? null,
                'unidad_medida' => $validated['unidad_medida'],
                'stock_minimo' => $validated['stock_minimo'],
                'stock_actual' => $validated['stock_actual'],
                'volumen_ml' => $validated['volumen_ml'],
                'imagen_url' => $validated['imagen_url'],
                'activo' => $validated['activo'] ?? true,
                'marca_id' => $validated['marca_id'],
                'categoria_id' => $validated['categoria_id'],
                'subcategoria_id' => $validated['subcategoria_id'] ?? null,
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
