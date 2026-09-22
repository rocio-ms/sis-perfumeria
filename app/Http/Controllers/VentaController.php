<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Lote;
use App\Models\TipoComprobante;
use App\Models\SerieComprobante;
use App\Models\TipoPago;
use App\Models\SesionCaja;
use App\Models\Kardex;
use App\Models\Tienda;
use App\Models\TipoMovimiento;
use App\Models\Salida;
use App\Models\DetalleSalida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class VentaController extends Controller
{
    /**
     * Listado de ventas
     */
    public function index(Request $request)
    {
        $ventas = Venta::with([
            'cliente',
            'usuario',
            'tipoComprobante',
            'tipoPago',
            'sesionCaja',
            'empresaEmisora',
            'serieComprobante',
            'detalles.producto',
            'detalles.lote',
        ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('serie', 'like', "%{$search}%")
                        ->orWhere(
                            'correlativo',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas('cliente', function ($cliente) use ($search) {
                            $cliente
                                ->where(
                                    'nombres',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'numero_documento',
                                    'like',
                                    "%{$search}%"
                                );
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Ventas/Index', [
            'ventas' => $ventas,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Formulario para registrar venta
     */
    public function create()
    {
        $clientes = Cliente::where('activo', true)
            ->orderBy('numero_documento')
            ->get();

        $productos = Producto::where('activo', true)
            ->with([
                'precioActual',
                'lotes' => function ($query) {
                    $query
                        ->where('cantidad_actual', '>', 0)
                        ->orderBy('fecha_vencimiento');
                },
            ])
            ->orderBy('nombre')
            ->get()
            ->map(function ($producto) {
                $precio = $producto->precioActual;

                return [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'codigo' => $producto->codigo,
                    'codigo_barras' => $producto->codigo_barras,

                    // Empresa y marca del producto
                    'empresa_id' => $producto->empresa_id,
                    'marca_id' => $producto->marca_id,

                    // Stock general
                    'stock_actual' => $producto->stock_actual,

                    // Precio
                    'precio_venta' => $precio?->precio_venta,
                    'tiene_descuento' => $precio?->tiene_descuento ?? false,
                    'descuento' => $precio?->descuento ?? 0,

                    // Lotes disponibles
                    'lotes' => $producto->lotes
                        ->map(function ($lote) {
                            return [
                                'id' => $lote->id,
                                'cantidad_actual' => (int) $lote->cantidad_actual,
                                'fecha_vencimiento' => $lote->fecha_vencimiento,
                            ];
                        })
                        ->values(),
                ];
            });

        $tiposComprobante = TipoComprobante::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $tiposPago = TipoPago::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $sesionCaja = SesionCaja::where(
            'user_id',
            Auth::id()
        )
            ->where('estado', 'abierta')
            ->first();

        return Inertia::render('Ventas/Crear', [
            'clientes' => $clientes,
            'productos' => $productos,
            'tiposComprobante' => $tiposComprobante,
            'tiposPago' => $tiposPago,
            'sesionCaja' => $sesionCaja,
        ]);
    }

    /**
     * Registrar venta
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => [
                'nullable',
                'exists:clientes,id',
            ],

            'sesion_caja_id' => [
                'required',
                'exists:sesion_cajas,id',
            ],

            'tipo_comprobante_id' => [
                'required',
                'exists:tipo_comprobantes,id',
            ],

            'fecha_emision' => [
                'required',
                'date',
            ],

            'moneda' => [
                'required',
                'string',
                'max:20',
            ],

            'forma_pago' => [
                'required',
                'in:contado,credito',
            ],

            'tipo_pago_id' => [
                'required',
                'exists:tipo_pagos,id',
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

            'detalles.*.lote_id' => [
                'required',
                'exists:lotes,id',
            ],

            'detalles.*.cantidad' => [
                'required',
                'integer',
                'min:1',
            ],

            'detalles.*.unidad_medida' => [
                'required',
                'string',
                'max:50',
            ],

            'detalles.*.precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.descuento' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'detalles.*.descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | CLIENTE
        |--------------------------------------------------------------------------
        */

        $clienteId = $validated['cliente_id'] ?? null;

        if (!$clienteId) {
            $cliente = Cliente::firstOrCreate(
                [
                    'numero_documento' => '00000000',
                ],
                [
                    'tipo_documento' => 'DNI',
                    'nombre' => 'CLIENTES VARIOS',
                    'razon_social_nombre' => 'CLIENTE ANÓNIMO',
                    'ap_paterno' => '',
                    'ap_materno' => '',
                    'nombres' => '',
                    'activo' => true,
                ]
            );

            $clienteId = $cliente->id;
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

        $venta = DB::transaction(function () use (
            $validated,
            $clienteId
        ) {
            /*
            |--------------------------------------------------------------------------
            | TIENDA
            |--------------------------------------------------------------------------
            */

            $tienda = Tienda::first();

            if (!$tienda) {
                throw new \Exception(
                    'No existe una tienda configurada.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | TIPO DE COMPROBANTE
            |--------------------------------------------------------------------------
            */

            $tipoComprobante = TipoComprobante::findOrFail(
                $validated['tipo_comprobante_id']
            );

            /*
            |--------------------------------------------------------------------------
            | OBTENER PRODUCTOS DE LA VENTA
            |--------------------------------------------------------------------------
            */

            $productoIds = collect($validated['detalles'])
                ->pluck('producto_id')
                ->unique()
                ->values();

            $productosVenta = Producto::whereIn(
                'id',
                $productoIds
            )
                ->get([
                    'id',
                    'empresa_id',
                    'marca_id',
                    'nombre',
                    'stock_actual',
                ]);

            /*
            |--------------------------------------------------------------------------
            | VALIDAR PRODUCTOS
            |--------------------------------------------------------------------------
            */

            if (
                $productosVenta->count() !==
                $productoIds->count()
            ) {
                throw new \Exception(
                    'Uno o más productos de la venta no existen.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR EMPRESA
            |--------------------------------------------------------------------------
            |
            | Todos los productos deben tener una empresa.
            |
            */

            $productoSinEmpresa = $productosVenta->first(
                fn($producto) => is_null($producto->empresa_id)
            );

            if ($productoSinEmpresa) {
                throw new \Exception(
                    "El producto {$productoSinEmpresa->nombre} no tiene una empresa asignada."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR MARCA
            |--------------------------------------------------------------------------
            |
            | La marca se obtiene directamente del producto.
            |
            */

            $productoSinMarca = $productosVenta->first(
                fn($producto) => is_null($producto->marca_id)
            );

            if ($productoSinMarca) {
                throw new \Exception(
                    "El producto {$productoSinMarca->nombre} no tiene una marca asignada."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | DETERMINAR EMPRESAS DE LA VENTA
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            |
            | Producto 1 → Empresa A
            | Producto 2 → Empresa A
            |
            | Resultado:
            | Venta de una sola empresa.
            |
            | --------------------------------
            |
            | Producto 1 → Empresa A
            | Producto 2 → Empresa B
            |
            | Resultado:
            | Venta mixta.
            |
            */

            $detallesPorEmpresa = collect($validated['detalles'])
                ->groupBy(function ($detalle) use ($productosVenta) {
                    return $productosVenta->firstWhere(
                        'id',
                        $detalle['producto_id']
                    )->empresa_id;
                });

            $ventas = collect();

            foreach ($detallesPorEmpresa as $empresaEmisoraId => $detallesEmpresa) {

                /*
            |--------------------------------------------------------------------------
            | BUSCAR SERIE DEL COMPROBANTE
            |--------------------------------------------------------------------------
            |
            | Cada empresa:
            |
            | tienda_id
            | empresa_id = empresa de la venta
            | tipo_comprobante_id
            |
            | Una venta con productos de varias empresas genera una serie
            | y un comprobante independiente para cada empresa.
            |
            */

                $serieQuery = SerieComprobante::where(
                    'tienda_id',
                    $tienda->id
                )
                    ->where(
                        'tipo_comprobante_id',
                        $validated['tipo_comprobante_id']
                    )
                    ->where('activo', true);

                $serieQuery->where(
                    'empresa_id',
                    $empresaEmisoraId
                );

                /*
            |--------------------------------------------------------------------------
            | BLOQUEAR SERIE
            |--------------------------------------------------------------------------
            |
            | Evita que dos ventas simultáneas obtengan
            | el mismo correlativo.
            |
            */


                $serieComprobante = $serieQuery
                    ->lockForUpdate()
                    ->first();

                /*
            |--------------------------------------------------------------------------
            | VALIDAR SERIE
            |--------------------------------------------------------------------------
            */

                if (!$serieComprobante) {
                    throw new \Exception(
                        "No existe una serie activa configurada para la empresa seleccionada y el comprobante {$tipoComprobante->nombre}."
                    );
                }

                /*
            |--------------------------------------------------------------------------
            | GENERAR CORRELATIVO
            |--------------------------------------------------------------------------
            |
            | El correlativo pertenece a la serie.
            |
            */

                $serieComprobante->correlativo_actual++;

                $correlativo = str_pad(
                    $serieComprobante->correlativo_actual,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                $serie = $serieComprobante->serie;

                $serieComprobante->save();

                /*
            |--------------------------------------------------------------------------
            | TOTALES
            |--------------------------------------------------------------------------
            */

                $opGravada = 0;
                $opExonerada = 0;
                $opInafecta = 0;
                $opGratuita = 0;
                $descuentoTotal = 0;
                $importeTotal = 0;

                /*
            |--------------------------------------------------------------------------
            | CREAR VENTA
            |--------------------------------------------------------------------------
            */

                $venta = Venta::create([
                    'cliente_id' => $clienteId,

                    'sesion_caja_id' =>
                    $validated['sesion_caja_id'],

                    'user_id' =>
                    Auth::id(),

                    'tipo_comprobante_id' =>
                    $validated['tipo_comprobante_id'],

                    'fecha_emision' =>
                    $validated['fecha_emision'],

                    /*
                |--------------------------------------------------------------------------
                | NUMERACIÓN GENERADA POR BACKEND
                |--------------------------------------------------------------------------
                */

                    'serie' => $serie,

                    'correlativo' => $correlativo,

                    'moneda' =>
                    $validated['moneda'],

                    'forma_pago' =>
                    $validated['forma_pago'],

                    'tipo_pago_id' =>
                    $validated['tipo_pago_id'],

                    /*
                |--------------------------------------------------------------------------
                | TOTALES INICIALES
                |--------------------------------------------------------------------------
                */

                    'op_gravada' => 0,
                    'op_exonerada' => 0,
                    'op_inafecta' => 0,
                    'op_gratuita' => 0,
                    'igv' => 0,
                    'descuento_total' => 0,
                    'importe_total' => 0,

                    /*
                |--------------------------------------------------------------------------
                | TIENDA
                |--------------------------------------------------------------------------
                */

                    'tienda_id' =>
                    $tienda->id,

                    /*
                |--------------------------------------------------------------------------
                | EMPRESA EMISORA
                |--------------------------------------------------------------------------
                |
                | Venta de una empresa:
                |     ID de empresa.
                |
                | Venta mixta:
                |     NULL.
                |
                */

                    'empresa_emisora_id' =>
                    $empresaEmisoraId,

                    /*
                |--------------------------------------------------------------------------
                | SERIE UTILIZADA
                |--------------------------------------------------------------------------
                */

                    'serie_comprobante_id' =>
                    $serieComprobante->id,

                    'estado_sunat' => 'pendiente',

                    'hash_cpe' => '',

                    'activo' => true,
                ]);

                /*
            |--------------------------------------------------------------------------
            | CREAR SALIDA
            |--------------------------------------------------------------------------
            */

                $tipoMovimientoSalida = TipoMovimiento::where(
                    'nombre',
                    'salida'
                )->firstOrFail();

                $salida = Salida::create([
                    'codigo_salida' =>
                    'SAL-' . $venta->id,

                    'tipo_salida' =>
                    'venta',

                    'fecha_salida' =>
                    now()->toDateString(),

                    'cliente_id' =>
                    $clienteId,

                    'observacion' =>
                    'Salida generada automáticamente por la venta '
                        . $venta->serie
                        . '-'
                        . $venta->correlativo,

                    'total' => 0,

                    'user_id' =>
                    Auth::id(),

                    'venta_id' =>
                    $venta->id,
                ]);

                $totalSalida = 0;

                /*
            |--------------------------------------------------------------------------
            | DETALLES DE LA VENTA
            |--------------------------------------------------------------------------
            */

                foreach ($detallesEmpresa as $detalle) {

                    /*
                |--------------------------------------------------------------------------
                | OBTENER PRODUCTO
                |--------------------------------------------------------------------------
                */

                    $producto = $productosVenta->firstWhere(
                        'id',
                        $detalle['producto_id']
                    );

                    if (!$producto) {
                        throw new \Exception(
                            'No se encontró el producto seleccionado.'
                        );
                    }

                    /*
                |--------------------------------------------------------------------------
                | VALIDAR MARCA
                |--------------------------------------------------------------------------
                */

                    if (is_null($producto->marca_id)) {
                        throw new \Exception(
                            "El producto {$producto->nombre} no tiene una marca asignada."
                        );
                    }

                    /*
                |--------------------------------------------------------------------------
                | LOTE
                |--------------------------------------------------------------------------
                */

                    $lote = Lote::with('detalleEntrada')
                        ->findOrFail(
                            $detalle['lote_id']
                        );

                    /*
                |--------------------------------------------------------------------------
                | VALIDAR LOTE
                |--------------------------------------------------------------------------
                */

                    if (
                        !$lote->detalleEntrada ||
                        $lote->detalleEntrada->producto_id !=
                        $producto->id
                    ) {
                        throw new \Exception(
                            'El lote seleccionado no pertenece al producto.'
                        );
                    }

                    /*
                |--------------------------------------------------------------------------
                | CANTIDAD
                |--------------------------------------------------------------------------
                */

                    $cantidad = (int) $detalle['cantidad'];

                    /*
                |--------------------------------------------------------------------------
                | VALIDAR STOCK
                |--------------------------------------------------------------------------
                */

                    if ($lote->cantidad_actual < $cantidad) {
                        throw new \Exception(
                            "Stock insuficiente para {$producto->nombre}. "
                                . "Disponible en el lote: "
                                . $lote->cantidad_actual
                                . "."
                        );
                    }

                    /*
                |--------------------------------------------------------------------------
                | COSTO DEL PRODUCTO
                |--------------------------------------------------------------------------
                */

                    $costoUnitario = (float)
                    $lote->detalleEntrada->precio_compra;

                    $costoTotal = round(
                        $costoUnitario * $cantidad,
                        2
                    );

                    $totalSalida += $costoTotal;

                    /*
                |--------------------------------------------------------------------------
                | PRECIO DE VENTA
                |--------------------------------------------------------------------------
                */

                    $precioUnitario =
                        (float) $detalle['precio_unitario'];

                    /*
                |--------------------------------------------------------------------------
                | DESCUENTO
                |--------------------------------------------------------------------------
                */

                    $descuento =
                        (float) ($detalle['descuento'] ?? 0);

                    /*
                |--------------------------------------------------------------------------
                | IMPORTE BRUTO
                |--------------------------------------------------------------------------
                */

                    $bruto = round(
                        $cantidad * $precioUnitario,
                        2
                    );

                    /*
                |--------------------------------------------------------------------------
                | VALIDAR DESCUENTO
                |--------------------------------------------------------------------------
                */

                    if ($descuento > $bruto) {
                        throw new \Exception(
                            'El descuento no puede ser mayor al importe bruto.'
                        );
                    }

                    /*
                |--------------------------------------------------------------------------
                | IMPORTE FINAL
                |--------------------------------------------------------------------------
                */

                    $importeFinal = round(
                        $bruto - $descuento,
                        2
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | IMPORTE DEL DETALLE
                    |--------------------------------------------------------------------------
                    |
                    | El precio de venta ya incluye IGV.
                    | No se descompone ni se calcula IGV.
                    |
                    */

                    $subtotal = $importeFinal;

                    $igv = 0;



                    /*
                |--------------------------------------------------------------------------
                | CREAR DETALLE DE VENTA
                |--------------------------------------------------------------------------
                */

                    DetalleVenta::create([
                        'venta_id' =>
                        $venta->id,

                        'producto_id' =>
                        $producto->id,

                        'marca_id' =>
                        $producto->marca_id,

                        'lote_id' =>
                        $lote->id,

                        'cantidad' =>
                        $cantidad,

                        'unidad_medida' =>
                        $detalle['unidad_medida'],

                        'precio_unitario' =>
                        $precioUnitario,

                        'descuento' =>
                        $descuento,

                        'igv' => 0,

                        'subtotal' =>
                        $subtotal,

                        'descripcion' =>
                        $detalle['descripcion']
                            ?? $producto->nombre,
                    ]);

                    /*
                |--------------------------------------------------------------------------
                | CREAR DETALLE DE SALIDA
                |--------------------------------------------------------------------------
                */

                    DetalleSalida::create([
                        'salida_id' =>
                        $salida->id,

                        'producto_id' =>
                        $producto->id,

                        'lote_id' =>
                        $lote->id,

                        'cantidad' =>
                        $cantidad,

                        'costo_unitario' =>
                        $costoUnitario,

                        'precio_unitario' =>
                        $precioUnitario,

                        'subtotal' =>
                        $costoTotal,
                    ]);

                    /*
                |--------------------------------------------------------------------------
                | KARDEX
                |--------------------------------------------------------------------------
                */

                    Kardex::create([
                        'fecha_movimiento' =>
                        now(),

                        'observacion' =>
                        'Venta '
                            . $venta->serie
                            . '-'
                            . $venta->correlativo,

                        'cantidad' =>
                        -$cantidad,

                        'costo_unitario' =>
                        $costoUnitario,

                        'costo_total' =>
                        $costoTotal,

                        'saldo_cantidad' =>
                        $producto->stock_actual
                            - $cantidad,

                        'saldo_costo_unitario' =>
                        $costoUnitario,

                        'tipo_movimiento_id' =>
                        $tipoMovimientoSalida->id,

                        'producto_id' =>
                        $producto->id,

                        'lote_id' =>
                        $lote->id,

                        'venta_id' =>
                        $venta->id,

                        'user_id' =>
                        Auth::id(),

                        'entrada_id' =>
                        null,

                        'salida_id' =>
                        $salida->id,
                    ]);

                    /*
                |--------------------------------------------------------------------------
                | ACTUALIZAR STOCK
                |--------------------------------------------------------------------------
                */

                    $lote->decrement(
                        'cantidad_actual',
                        $cantidad
                    );

                    $producto->decrement(
                        'stock_actual',
                        $cantidad
                    );

                    /*
                |--------------------------------------------------------------------------
                | ACUMULAR TOTALES
                |--------------------------------------------------------------------------
                */

                    // $opGravada += $subtotal;

                    $descuentoTotal += $descuento;

                    $importeTotal += $importeFinal;
                }

                /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR TOTAL DE SALIDA
            |--------------------------------------------------------------------------
            */

                $salida->update([
                    'total' => round(
                        $totalSalida,
                        2
                    ),
                ]);

                /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR TOTALES DE VENTA
            |--------------------------------------------------------------------------
            */

                $venta->update([
                    'op_gravada' => 0,

                    'op_exonerada' => 0,

                    'op_inafecta' => 0,

                    'op_gratuita' => 0,

                    'igv' => 0,

                    'descuento_total' =>
                    round($descuentoTotal, 2),

                    'importe_total' =>
                    round($importeTotal, 2),
                ]);

                $ventas->push($venta);
            }

            return $ventas;
        });

        /*
        |--------------------------------------------------------------------------
        | REDIRECCIÓN AL COMPROBANTE
        |--------------------------------------------------------------------------
        */

        return redirect()->route(
            'ventas.comprobante',
            $venta->first()->id
        );
    }

    /**
     * Mostrar venta
     */
    public function show(Venta $venta)
    {
        $venta->load([
            'detalles.producto',
            'cliente',
            'usuario',
            'tipoComprobante',
            'tipoPago',
            'tienda',
        ]);

        return Inertia::render('Ventas/Show', [
            'venta' => $venta,
            'tienda' => $venta->tienda,
        ]);
    }

    /**
     * Editar venta
     */
    public function edit(Venta $venta)
    {
        $venta->load([
            'cliente',
            'detalles.producto',
            'detalles.lote',
        ]);

        $clientes = Cliente::where('activo', true)
            ->orderBy('numero_documento')
            ->get();

        $productos = Producto::where('activo', true)
            ->with([
                'precioActual',
                'lotes' => function ($query) {
                    $query
                        ->where('cantidad_actual', '>', 0)
                        ->orderBy('fecha_vencimiento');
                },
            ])
            ->orderBy('nombre')
            ->get()
            ->map(function ($producto) {
                $precio = $producto->precioActual;

                return [
                    'id' =>
                    $producto->id,

                    'nombre' =>
                    $producto->nombre,

                    'codigo' =>
                    $producto->codigo,

                    'codigo_barras' =>
                    $producto->codigo_barras,

                    'empresa_id' =>
                    $producto->empresa_id,

                    'marca_id' =>
                    $producto->marca_id,

                    'stock_actual' =>
                    $producto->stock_actual,

                    'precio_venta' =>
                    $precio?->precio_venta,

                    'tiene_descuento' =>
                    $precio?->tiene_descuento ?? false,

                    'descuento' =>
                    $precio?->descuento ?? 0,

                    'lotes' =>
                    $producto->lotes
                        ->map(function ($lote) {
                            return [
                                'id' =>
                                $lote->id,

                                'cantidad_actual' =>
                                (int) $lote->cantidad_actual,

                                'fecha_vencimiento' =>
                                $lote->fecha_vencimiento,
                            ];
                        })
                        ->values(),
                ];
            });

        $tiposComprobante = TipoComprobante::where(
            'activo',
            true
        )
            ->orderBy('nombre')
            ->get();

        $tiposPago = TipoPago::where(
            'activo',
            true
        )
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Ventas/Crear', [
            'venta' => $venta,
            'clientes' => $clientes,
            'productos' => $productos,
            'tiposComprobante' => $tiposComprobante,
            'tiposPago' => $tiposPago,
        ]);
    }

    /**
     * Actualizar venta
     */
    public function update(
        Request $request,
        Venta $venta
    ) {
        $validated = $request->validate([
            'cliente_id' => [
                'required',
                'exists:clientes,id',
            ],

            'forma_pago' => [
                'required',
                'in:contado,credito',
            ],

            'tipo_pago_id' => [
                'required',
                'exists:tipo_pagos,id',
            ],
        ]);

        $venta->update($validated);

        return redirect()
            ->route('ventas.index')
            ->with(
                'success',
                'Venta actualizada correctamente.'
            );
    }

    /**
     * Anular venta
     */
    public function destroy(Venta $venta)
    {
        $venta->update([
            'activo' => false,
        ]);

        return redirect()
            ->route('ventas.index')
            ->with(
                'success',
                'Venta anulada correctamente.'
            );
    }

    /**
     * Mostrar comprobante
     */
    public function comprobante(Venta $venta)
    {
        $venta->load([
            'cliente',
            'usuario',
            'tipoComprobante',
            'tipoPago',
            'sesionCaja',
            'detalles.producto',
            'detalles.lote',
            'empresaEmisora',
            'serieComprobante',
        ]);

        $tienda = Tienda::first();

        return Inertia::render('Ventas/Comprobante', [
            'venta' => $venta,
            'tienda' => $tienda,
        ]);
    }
}
