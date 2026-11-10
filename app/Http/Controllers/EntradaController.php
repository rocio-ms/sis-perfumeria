<?php

namespace App\Http\Controllers;

use App\Models\DetalleEntrada;
use App\Models\Entrada;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Lote;
use App\Models\PrecioProducto;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Kardex;
use App\Models\Notificacion;
use App\Models\TipoMovimiento;
use App\Models\Empresa;
use App\Models\Subcategoria;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class EntradaController extends Controller
{
    private const MENSAJES_FECHAS = [
        'fecha_ingreso.required' => 'Ingresa la fecha de ingreso.',
        'fecha_ingreso.date' => 'La fecha de ingreso no es válida.',
        'detalles.*.fecha_vencimiento.required_if' => 'Selecciona el mes de vencimiento.',
        'detalles.*.fecha_vencimiento.date' => 'La fecha de vencimiento no es válida.',
    ];

    public function buscarProductos(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $termino = trim($validated['q'] ?? '');

        if ($termino === '') {
            return response()->json(['productos' => []]);
        }

        $productos = Producto::where('activo', true)
            ->where(function ($query) use ($termino) {
                $query->where('nombre', 'like', "{$termino}%")
                    ->orWhere('codigo', 'like', "{$termino}%")
                    ->orWhere('codigo_barras', 'like', "{$termino}%");
            })
            ->with('categoria:id,nombre')
            ->limit(20)
            ->get([
                'id',
                'nombre',
                'codigo',
                'codigo_barras',
                'categoria_id',
                'unidad_medida',
                'stock_actual',
            ]);

        return response()->json(['productos' => $productos]);
    }

    public function buscarPorCodigoBarras(Request $request)
    {
        $validated = $request->validate([
            'codigo_barras' => ['required', 'string', 'max:100'],
        ]);

        $producto = Producto::where('activo', true)
            ->where('codigo_barras', $validated['codigo_barras'])
            ->with('categoria:id,nombre')
            ->first([
                'id',
                'nombre',
                'codigo',
                'codigo_barras',
                'categoria_id',
                'unidad_medida',
                'stock_actual',
            ]);

        return response()->json([
            'producto' => $producto,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
            'sort' => [
                'nullable',
                Rule::in([
                    'id',
                    'codigo_ingreso',
                    'tipo_comprobante',
                    'numero_comprobante',
                    'proveedor.nombre_comercial',
                    'fecha_ingreso',
                    'total_compra',
                ]),
            ],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim($validated['search'] ?? '');
        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';

        $entradasQuery = Entrada::with([
            'proveedor',
            'user',
            'detalles.producto',
            'detalles.lotes',
        ]);

        if ($search !== '') {
            $termino = "%{$search}%";
            $entradasQuery->where(function ($query) use ($termino) {
                $query->where('codigo_ingreso', 'like', $termino)
                    ->orWhere('tipo_comprobante', 'like', $termino)
                    ->orWhere('numero_comprobante', 'like', $termino)
                    ->orWhereHas('proveedor', fn ($proveedorQuery) => $proveedorQuery
                        ->where('nombre_comercial', 'like', $termino)
                        ->orWhere('razon_social', 'like', $termino));
            });
        }

        if (!empty($validated['fecha_desde'])) {
            $entradasQuery->whereDate('fecha_ingreso', '>=', $validated['fecha_desde']);
        }

        if (!empty($validated['fecha_hasta'])) {
            $entradasQuery->whereDate('fecha_ingreso', '<=', $validated['fecha_hasta']);
        }

        if ($sort === 'proveedor.nombre_comercial') {
            $entradasQuery->orderBy(
                Proveedor::select('nombre_comercial')
                    ->whereColumn('proveedors.id', 'entradas.proveedor_id'),
                $direction,
            );
        } else {
            $sortColumn = in_array($sort, [
                'codigo_ingreso',
                'tipo_comprobante',
                'numero_comprobante',
                'fecha_ingreso',
                'total_compra',
            ], true) ? $sort : 'id';
            $entradasQuery->orderBy($sortColumn, $direction);
        }

        $entradas = $entradasQuery
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        $proveedores = Proveedor::where('activo', true)
            ->get();

        $ultimoId = Entrada::max('id') ?? 0;

        $codigoIngreso = 'ING-' . str_pad(
            $ultimoId + 1,
            6,
            '0',
            STR_PAD_LEFT
        );

        return Inertia::render('Entradas/Index', [
            'entradas' => $entradas,
            'proveedores' => $proveedores,
            'productos' => collect(),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
            'marcas' => Marca::where('activo', true)->orderBy('nombre')->get(),
            'subcategorias' => Subcategoria::where('activo', true)->orderBy('nombre')->get(),
            'ubicaciones' => Producto::UBICACIONES,
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(),
            'codigoIngreso' => $codigoIngreso,
            'filtros' => [
                'search' => $search,
                'fecha_ingreso' => [
                    $validated['fecha_desde'] ?? '',
                    $validated['fecha_hasta'] ?? '',
                ],
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return Inertia::render('Entradas/Form', [
            'proveedores' => Proveedor::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'productos' => Producto::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'marcas' => Marca::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'subcategorias' => Subcategoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'ubicaciones' => Producto::UBICACIONES,

            'empresas' => Empresa::where('activo', true)
                ->orderBy('razon_social')
                ->get(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(Entrada $entrada)
    {
        $entrada->load([
            'proveedor',
            'user',
            'detalles.producto',
            'detalles.lotes',
        ]);

        return Inertia::render('Entradas/Show', [
            'entrada' => $entrada,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([

            'tipo_comprobante' => [
                'nullable',
                'in:factura,boleta,guia_remision,nota_compra,orden_compra',
            ],

            'numero_comprobante' => [
                'nullable',
                'string',
                'max:100',
            ],

            'fecha_ingreso' => [
                'required',
                'date',
            ],

            'observacion' => [
                'nullable',
                'string',
            ],

            'proveedor_id' => [
                'nullable',
                'exists:proveedors,id',
            ],

            'detalles' => [
                'required',
                'array',
                'min:1',
            ],
            'detalles.*' => ['required', 'array'],

            'detalles.*.producto_id' => [
                'required',
                'exists:productos,id',
            ],

            'detalles.*.codigo_barras_lote' => [
                'nullable',
                'string',
                'max:50',
            ],

            'detalles.*.cantidad' => [
                'required',
                'integer',
                'min:1',
            ],

            'detalles.*.precio_compra' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.precio_venta' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.tiene_descuento' => [
                'nullable',
                'boolean',
            ],

            'detalles.*.descuento' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'detalles.*.tiene_vencimiento' => ['sometimes', 'boolean'],
            'detalles.*.fecha_vencimiento' => $this->reglasFechaVencimiento(),
        ], self::MENSAJES_FECHAS);

        $validated = $this->normalizarFechas($validated);


        DB::transaction(function () use ($validated, $request) {

            /*
            |--------------------------------------------------------------------------
            | CÓDIGO DE INGRESO
            |--------------------------------------------------------------------------
            */

            $ultimoId = Entrada::max('id') ?? 0;

            $codigoIngreso = 'ING-' . str_pad(
                $ultimoId + 1,
                6,
                '0',
                STR_PAD_LEFT
            );


            /*
            |--------------------------------------------------------------------------
            | TOTAL DE COMPRA
            |--------------------------------------------------------------------------
            */

            $totalCompra = 0;

            foreach ($validated['detalles'] as $detalle) {

                $totalCompra +=
                    $detalle['cantidad'] *
                    $detalle['precio_compra'];
            }


            /*
            |--------------------------------------------------------------------------
            | CREAR ENTRADA
            |--------------------------------------------------------------------------
            */

            $entrada = Entrada::create([
                'codigo_ingreso' => $codigoIngreso,
                'tipo_comprobante' => $validated['tipo_comprobante'] ?? null,
                'numero_comprobante' => $validated['numero_comprobante'] ?? null,
                'fecha_ingreso' => $validated['fecha_ingreso'],
                'total_compra' => $totalCompra,
                'observacion' => $validated['observacion'] ?? null,
                'proveedor_id' => $validated['proveedor_id'] ?? null,
                'user_id' => $request->user()->id,
            ]);


            /*
            |--------------------------------------------------------------------------
            | DETALLES
            |--------------------------------------------------------------------------
            */

            foreach ($validated['detalles'] as $detalle) {

                $cantidad = (int) $detalle['cantidad'];

                $precioCompra = (float) $detalle['precio_compra'];

                $precioVenta = (float) $detalle['precio_venta'];

                $tieneDescuento =
                    $detalle['tiene_descuento'] ?? false;

                /*
                |--------------------------------------------------------------------------
                | DESCUENTO
                |--------------------------------------------------------------------------
                */

                $descuento = $tieneDescuento
                    ? (float) ($detalle['descuento'] ?? 0)
                    : 0;

                if ($descuento > $precioVenta) {
                    abort(
                        422,
                        'El descuento no puede ser mayor al precio de venta.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | SUBTOTAL DE COMPRA
                |--------------------------------------------------------------------------
                */

                $subtotal = $cantidad * $precioCompra;


                /*
                |--------------------------------------------------------------------------
                | DETALLE DE ENTRADA
                |--------------------------------------------------------------------------
                */

                $detalleEntrada = DetalleEntrada::create([
                    'entrada_id' => $entrada->id,
                    'producto_id' => $detalle['producto_id'],
                    'cantidad' => $cantidad,
                    'precio_compra' => $precioCompra,
                    'subtotal' => $subtotal,

                    'precio_venta' => $detalle['precio_venta'],

                    'tiene_descuento' => $tieneDescuento,

                    'descuento' => $descuento,
                ]);

                PrecioProducto::where('producto_id', $detalle['producto_id'])
                    ->where('activo', true)
                    ->update([
                        'activo' => false,
                    ]);

                PrecioProducto::create([
                    'producto_id' => $detalle['producto_id'],
                    'precio_venta' => $precioVenta,
                    'tiene_descuento' => $tieneDescuento,
                    'descuento' => $descuento,
                    'activo' => true,
                ]);
                /*
                |--------------------------------------------------------------------------
                | PRODUCTO
                |--------------------------------------------------------------------------
                */

                $producto = Producto::findOrFail(
                    $detalle['producto_id']
                );

                /*
                |--------------------------------------------------------------------------
                | GENERAR LOTE
                |--------------------------------------------------------------------------
                */

                $ultimoLoteId = Lote::max('id') ?? 0;

                $numeroLote = 'LOT-' . str_pad(
                    $ultimoLoteId + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                $lote = Lote::create([
                    'numero_lote' => $numeroLote,

                    'fecha_ingreso' =>
                    $validated['fecha_ingreso'],

                    'fecha_vencimiento' =>
                    $detalle['fecha_vencimiento'] ?? null,

                    'cantidad_inicial' => $cantidad,

                    'cantidad_actual' => $cantidad,

                    'codigo_barras' =>
                    $detalle['codigo_barras_lote'] ?? null,

                    'detalle_entrada_id' =>
                    $detalleEntrada->id,
                ]);



                /*
                |--------------------------------------------------------------------------
                | ACTUALIZAR STOCK
                |--------------------------------------------------------------------------
                */

                $stockAnterior = $producto->stock_actual;

                $producto->increment(
                    'stock_actual',
                    $cantidad
                );

                $stockNuevo = $stockAnterior + $cantidad;

                // Resolver automáticamente la notificación de stock bajo
                if ($stockNuevo > $producto->stock_minimo) {
                    Notificacion::where('tipo', 'stock_bajo')
                        ->where('referencia_tipo', 'Producto')
                        ->where('referencia_id', $producto->id)
                        ->where('solucionada', false)
                        ->update([
                            'solucionada' => true,
                            'solucionada_at' => now(),
                        ]);
                }

                //Registrar kardex

                $tipoMovimiento = TipoMovimiento::where('tipo', 'entrada')
                    ->firstOrFail();

                $saldoCantidad = $producto->stock_actual;

                Kardex::create([
                    'producto_id' => $producto->id,
                    'lote_id' => $lote->id,
                    'tipo_movimiento_id' => $tipoMovimiento->id,
                    'entrada_id' => $entrada->id,
                    'salida_id' => null,
                    'venta_id' => null,
                    'user_id' => $request->user()->id,
                    'fecha_movimiento' => $validated['fecha_ingreso'],
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'costo_unitario' => $precioCompra,
                    'costo_total' => $cantidad * $precioCompra,
                    'saldo_cantidad' => $saldoCantidad,
                    'saldo_costo_unitario' => $precioCompra,
                ]);
            }
        });


        return redirect()
            ->route('entradas.index')
            ->with(
                'success',
                'Entrada registrada correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT DATA
    |--------------------------------------------------------------------------
    */

    public function editData(Entrada $entrada)
    {
        $entrada->load([
            'proveedor',
            'detalles.producto',
            'detalles.lotes',
            'user',
        ]);

        return response()->json($entrada);
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(Entrada $entrada)
    {
        $entrada->load([
            'detalles.producto',
            'detalles.lotes',
        ]);

        return Inertia::render('Entradas/Form', [
            'entrada' => $entrada,

            'proveedores' => Proveedor::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'productos' => Producto::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),

            'marcas' => Marca::where('activo', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Entrada $entrada)
    {
        $validated = $request->validate([

            'tipo_comprobante' => [
                'required',
                'in:factura,boleta,guia_remision,nota_compra,orden_compra',
            ],

            'numero_comprobante' => [
                'nullable',
                'string',
                'max:255',
            ],

            'fecha_ingreso' => [
                'required',
                'date',
            ],

            'proveedor_id' => [
                'nullable',
                'exists:proveedors,id',
            ],

            'observacion' => [
                'nullable',
                'string',
            ],

            'detalles' => [
                'required',
                'array',
                'min:1',
            ],
            'detalles.*' => ['required', 'array'],

            'detalles.*.producto_id' => [
                'required',
                'exists:productos,id',
            ],

            'detalles.*.codigo_barras_lote' => [
                'nullable',
                'string',
                'max:50',
            ],

            'detalles.*.cantidad' => [
                'required',
                'integer',
                'min:1',
            ],

            'detalles.*.precio_compra' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.precio_venta' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.tiene_descuento' => [
                'nullable',
                'boolean',
            ],

            'detalles.*.descuento' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'detalles.*.tiene_vencimiento' => ['sometimes', 'boolean'],
            'detalles.*.fecha_vencimiento' => $this->reglasFechaVencimiento(),
        ], self::MENSAJES_FECHAS);

        $validated = $this->normalizarFechas($validated);


        DB::transaction(function () use ($validated, $entrada) {

            if ($entrada->detalles()->whereHas('lotes.kardexs')->exists()) {
                throw ValidationException::withMessages([
                    'detalles' => 'No se puede editar esta entrada porque sus lotes ya tienen movimientos en el Kardex. El historial de inventario debe conservarse.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | GUARDAR DETALLES ANTERIORES
            |--------------------------------------------------------------------------
            */

            $detallesAnteriores = $entrada->detalles;


            /*
            |--------------------------------------------------------------------------
            | REVERTIR STOCK
            |--------------------------------------------------------------------------
            */

            foreach ($detallesAnteriores as $detalle) {

                Producto::where(
                    'id',
                    $detalle->producto_id
                )->decrement(
                    'stock_actual',
                    $detalle->cantidad
                );
            }


            /*
            |--------------------------------------------------------------------------
            | ELIMINAR DETALLES
            |--------------------------------------------------------------------------
            |
            | Si detalle_entrada -> lote tiene onDelete('cascade'),
            | también se eliminarán sus lotes.
            |
            */

            $entrada->detalles()->delete();


            /*
            |--------------------------------------------------------------------------
            | TOTAL DE COMPRA
            |--------------------------------------------------------------------------
            */

            $totalCompra = 0;

            foreach ($validated['detalles'] as $detalle) {

                $totalCompra +=
                    $detalle['cantidad'] *
                    $detalle['precio_compra'];
            }


            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR CABECERA
            |--------------------------------------------------------------------------
            */

            $entrada->update([
                'tipo_comprobante' =>
                $validated['tipo_comprobante'],

                'numero_comprobante' =>
                $validated['numero_comprobante'] ?? null,

                'fecha_ingreso' =>
                $validated['fecha_ingreso'],

                'total_compra' =>
                $totalCompra,

                'observacion' =>
                $validated['observacion'] ?? null,

                'proveedor_id' =>
                $validated['proveedor_id'] ?? null,
            ]);


            /*
            |--------------------------------------------------------------------------
            | CREAR NUEVOS DETALLES
            |--------------------------------------------------------------------------
            */

            foreach ($validated['detalles'] as $detalle) {

                $cantidad = (int) $detalle['cantidad'];

                $precioCompra =
                    (float) $detalle['precio_compra'];

                $precioVenta =
                    (float) $detalle['precio_venta'];

                $tieneDescuento =
                    $detalle['tiene_descuento'] ?? false;

                $descuento = $tieneDescuento
                    ? (float) ($detalle['descuento'] ?? 0)
                    : 0;


                /*
                |--------------------------------------------------------------------------
                | VALIDAR DESCUENTO
                |--------------------------------------------------------------------------
                */

                if ($descuento > $precioVenta) {
                    abort(
                        422,
                        'El descuento no puede ser mayor al precio de venta.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | SUBTOTAL
                |--------------------------------------------------------------------------
                */

                $subtotal =
                    $cantidad *
                    $precioCompra;


                /*
                |--------------------------------------------------------------------------
                | DETALLE
                |--------------------------------------------------------------------------
                */

                $detalleEntrada = DetalleEntrada::create([
                    'entrada_id' => $entrada->id,

                    'producto_id' =>
                    $detalle['producto_id'],

                    'cantidad' =>
                    $cantidad,

                    'precio_compra' =>
                    $precioCompra,

                    'subtotal' =>
                    $subtotal,

                    'precio_venta' =>
                    $precioVenta,

                    'tiene_descuento' =>
                    $tieneDescuento,

                    'descuento' =>
                    $descuento,
                ]);


                /*
                |--------------------------------------------------------------------------
                | PRODUCTO
                |--------------------------------------------------------------------------
                */

                $producto = Producto::findOrFail(
                    $detalle['producto_id']
                );


                /*
                |--------------------------------------------------------------------------
                | ACTUALIZAR PRECIO VIGENTE
                |--------------------------------------------------------------------------
                */

                PrecioProducto::where(
                    'producto_id',
                    $producto->id
                )
                    ->where('activo', true)
                    ->update([
                        'activo' => false,
                    ]);


                /*
                |--------------------------------------------------------------------------
                | NUEVO PRECIO
                |--------------------------------------------------------------------------
                */

                PrecioProducto::create([
                    'producto_id' =>
                    $producto->id,

                    'precio_venta' =>
                    $precioVenta,

                    'tiene_descuento' =>
                    $tieneDescuento,

                    'descuento' =>
                    $descuento,

                    'activo' =>
                    true,
                ]);


                /*
                |--------------------------------------------------------------------------
                | CREAR LOTE
                |--------------------------------------------------------------------------
                */

                $ultimoLoteId =
                    Lote::max('id') ?? 0;

                $numeroLote =
                    'LOT-' . str_pad(
                        $ultimoLoteId + 1,
                        6,
                        '0',
                        STR_PAD_LEFT
                    );


                Lote::create([
                    'numero_lote' =>
                    $numeroLote,

                    'fecha_ingreso' =>
                    $validated['fecha_ingreso'],

                    'fecha_vencimiento' =>
                    $detalle['fecha_vencimiento'] ?? null,

                    'cantidad_inicial' =>
                    $cantidad,

                    'cantidad_actual' =>
                    $cantidad,

                    'codigo_barras' =>
                    $detalle['codigo_barras_lote'] ?? null,

                    'detalle_entrada_id' =>
                    $detalleEntrada->id,
                ]);


                /*
                |--------------------------------------------------------------------------
                | ACTUALIZAR STOCK
                |--------------------------------------------------------------------------
                */

                $producto->increment(
                    'stock_actual',
                    $cantidad
                );
            }
        });


        return redirect()
            ->route('entradas.index')
            ->with(
                'success',
                'Entrada actualizada correctamente.'
            );
    }

    private function reglasFechaVencimiento(): array
    {
        return [
            'bail',
            'required_if:detalles.*.tiene_vencimiento,true',
            'nullable',
            'date',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->finDeMes($value) <= Carbon::today()->toDateString()) {
                    $fail('La fecha de vencimiento debe ser posterior a hoy.');
                }
            },
        ];
    }

    private function normalizarFechas(array $validated): array
    {
        $validated['fecha_ingreso'] = Carbon::parse($validated['fecha_ingreso'])->toDateString();

        foreach ($validated['detalles'] as $index => $detalle) {
            if (isset($detalle['fecha_vencimiento'])) {
                $validated['detalles'][$index]['fecha_vencimiento'] = $this->finDeMes($detalle['fecha_vencimiento']);
            }
        }

        return $validated;
    }

    private function finDeMes(string $fecha): string
    {
        return Carbon::parse($fecha)->endOfMonth()->toDateString();
    }
}
