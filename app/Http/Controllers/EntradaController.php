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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class EntradaController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {

        $buscarProducto = trim($request->input('buscar_producto', ''));

        // Busqueda rápida de productos

        if ($request->has('buscar_producto')) {

            $productos = collect();

            if ($buscarProducto !== '') {

                $productos = Producto::where('activo', true)
                    ->where(function ($query) use ($buscarProducto) {
                        $query->where('nombre', 'like', "{$buscarProducto}%")
                            ->orWhere('codigo', 'like', "{$buscarProducto}%")
                            ->orWhere('codigo_barras', 'like', "{$buscarProducto}%");
                    })
                    ->select([
                        'id',
                        'nombre',
                        'codigo',
                        'codigo_barras',
                        'unidad_medida',
                        'stock_actual',
                    ])
                    ->limit(20)
                    ->get();
            }

            return Inertia::render('Entradas/Index', [
                'productos' => $productos,
            ]);
        }

        $entradas = Entrada::with([
            'proveedor',
            'user',
            'detalles.producto',
            'detalles.lotes',
        ])
            ->latest()
            ->get();

        $proveedores = Proveedor::where('activo', true)
            ->get();
        $categorias = Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $marcas = Marca::where('activo', true)
            ->orderBy('nombre')
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
            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'marcas' => Marca::where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'codigoIngreso' => $codigoIngreso,
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

        if ($request->filled('fecha_ingreso')) {
            $request->merge([
                'fecha_ingreso' => Carbon::parse(
                    $request->fecha_ingreso
                )->format('Y-m-d'),
            ]);
        }

        if ($request->has('detalles')) {

            $detalles = $request->input('detalles');

            foreach ($detalles as $index => $detalle) {

                if (!empty($detalle['fecha_vencimiento'])) {

                    $detalles[$index]['fecha_vencimiento'] =
                        Carbon::parse(
                            $detalle['fecha_vencimiento']
                        )->endOfMonth()->format('Y-m-d');
                }
            }

            $request->merge([
                'detalles' => $detalles,
            ]);
        }

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

            'detalles.*.producto_id' => [
                'required',
                'exists:productos,id',
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

            'detalles.*.fecha_vencimiento' => [
                'nullable',
                'date',
                'after:today',
            ],
        ]);


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
                'tipo_comprobante' => $validated['tipo_comprobante'],
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
        if ($request->filled('fecha_ingreso')) {
            $request->merge([
                'fecha_ingreso' => Carbon::parse(
                    $request->fecha_ingreso
                )->format('Y-m-d'),
            ]);
        }

        if ($request->has('detalles')) {
            $detalles = $request->input('detalles');

            foreach ($detalles as $index => $detalle) {
                $detalles[$index]['fecha_vencimiento'] =
                    !empty($detalle['fecha_vencimiento'])
                    ? Carbon::parse(
                        $detalle['fecha_vencimiento']
                    )->endOfMonth()->format('Y-m-d')
                    : null;
            }

            $request->merge([
                'detalles' => $detalles,
            ]);
        }

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

            'detalles.*.producto_id' => [
                'required',
                'exists:productos,id',
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

            'detalles.*.fecha_vencimiento' => [
                'nullable',
                'date',
                'after:today',
            ],
        ]);


        DB::transaction(function () use ($validated, $entrada) {

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
}
