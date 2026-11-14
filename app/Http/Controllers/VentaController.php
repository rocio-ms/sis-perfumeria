<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Cliente;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\PrecioProducto;
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
use App\Support\BusquedaNombre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
            'comprobantes.cliente',
            'comprobantes.tipoComprobante',
            'comprobantes.detalles.producto',
        ])
            ->withCount('comprobantes')
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
            ->get();

        return Inertia::render('Ventas/Index', [
            'ventas' => $ventas,
            'tienda' => Tienda::first(),
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
        $comprobantesEmitidosIds = session()->pull(
            'comprobantes_emitidos_ids',
            []
        );

        $sesionCaja = SesionCaja::where('user_id', Auth::id())
            ->where('estado', 'abierta')
            ->first();

        $clientes = Cliente::where('activo', true)
            ->orderBy('numero_documento')
            ->get();

        $productos = $this->productosParaVenta();

        $tiposComprobante = TipoComprobante::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $tiposPago = TipoPago::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $comprobantesEmitidos = empty($comprobantesEmitidosIds)
            ? collect()
            : Venta::whereIn('id', $comprobantesEmitidosIds)
                ->where('activo', true)
                ->with([
                    'cliente',
                    'usuario',
                    'tipoComprobante',
                    'tipoPago',
                    'sesionCaja',
                    'detalles.producto',
                    'detalles.lote',
                    'empresaEmisora',
                    'serieComprobante',
                ])
                ->orderBy('id')
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
            'categorias' => Categoria::where('activo', true)
                ->select(['id', 'nombre'])
                ->withSum([
                    'productos as stock_total' => fn ($query) => $query->disponibleParaVenta(),
                ], 'stock_actual')
                ->withCasts(['stock_total' => 'integer'])
                ->orderBy('nombre')
                ->get(),
            'tiposComprobante' => $tiposComprobante,
            'tiposPago' => $tiposPago,
            'sesionCaja' => $sesionCaja,
            'cajaAbierta' => $sesionCaja !== null,
            'comprobantesEmitidos' => $comprobantesEmitidos,
            'tienda' => Tienda::first(),
        ]);
    }

    public function buscarProductos(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
        ]);

        $codigo = trim($validated['search'] ?? '');
        if ($codigo !== '') {
            $coincidencias = Producto::conCodigoDeEtiqueta($codigo)->limit(2)->get();

            if ($coincidencias->count() > 1) {
                return response()->json([
                    'productos' => [],
                    'aviso' => 'El código corresponde a varios productos. Busca por nombre o revisa sus etiquetas.',
                ]);
            }

            if ($producto = $coincidencias->first()) {
                $codigoNormalizado = mb_strtolower($codigo);
                $esCodigoDeBarras = $codigoNormalizado === mb_strtolower($producto->codigo_barras ?? '');
                $esCodigoInterno = $codigoNormalizado === mb_strtolower($producto->codigo);
                $codigoDeLote = $esCodigoDeBarras || $esCodigoInterno ? null : $codigo;
                $productos = $this->productosParaVenta(productoId: $producto->id, codigoDeLote: $codigoDeLote);

                return response()->json([
                    'productos' => $productos,
                    'producto_exacto_id' => $productos->isNotEmpty() ? $producto->id : null,
                    'etiqueta_exacta' => $esCodigoDeBarras || $codigoDeLote !== null,
                    'aviso' => $productos->isEmpty()
                        ? "Encontramos «{$producto->nombre}», pero no está disponible para vender. Revisa su estado, stock, precio vigente y el lote."
                        : null,
                ]);
            }
        }

        return response()->json([
            'productos' => $this->productosParaVenta($codigo, $validated['categoria_id'] ?? null),
        ]);
    }

    private function productosParaVenta(
        string $search = '',
        ?int $categoriaId = null,
        ?int $productoId = null,
        ?string $codigoDeLote = null,
    )
    {
        $consulta = Producto::query()
            ->select([
                'id',
                'codigo',
                'codigo_barras',
                'nombre',
                'stock_actual',
                'stock_minimo',
                'imagen_url',
                'categoria_id',
                'empresa_id',
                'marca_id',
            ])
            ->disponibleParaVenta()
            ->when($productoId, fn ($query) => $query->whereKey($productoId))
            ->when($codigoDeLote !== null, fn ($query) => $query->whereHas('lotes', fn ($lotes) => $lotes
                ->disponibleParaVenta()->where('lotes.codigo_barras', $codigoDeLote)))
            ->when(
                $categoriaId,
                fn($query) => $query->where('categoria_id', $categoriaId),
            )
            ->orderBy('productos.nombre')
            ->orderBy('productos.id');

        if ($search === '' && !$categoriaId && !$productoId) {
            $consulta->limit(24);
        }

        $productos = $consulta->get();
        if ($search !== '') {
            $nombreBuscado = BusquedaNombre::normalizar($search);
            $codigoBuscado = mb_strtolower($search);
            $productos = $productos->filter(fn (Producto $producto) =>
                ($nombreBuscado !== '' && str_contains(BusquedaNombre::normalizar($producto->nombre), $nombreBuscado))
                || str_starts_with(mb_strtolower($producto->codigo), $codigoBuscado)
                || str_starts_with(mb_strtolower($producto->codigo_barras ?? ''), $codigoBuscado)
            );
        }

        $productos->load([
            'precioActual',
            'lotes' => function ($query) use ($codigoDeLote) {
                $query->disponibleParaVenta()
                    ->when($codigoDeLote !== null, fn ($lotes) => $lotes->where('lotes.codigo_barras', $codigoDeLote))
                    ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('fecha_vencimiento')
                    ->orderBy('lotes.id');
            },
        ]);

        return $productos
            ->map(function ($producto) {
                $precio = $producto->precioActual;
                $lote = $producto->lotes->first();

                return [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'codigo' => $producto->codigo,
                    'codigo_barras' => $producto->codigo_barras,
                    'categoria_id' => $producto->categoria_id,
                    'empresa_id' => $producto->empresa_id,
                    'marca_id' => $producto->marca_id,
                    'stock_actual' => $producto->stock_actual,
                    'stock_minimo' => $producto->stock_minimo,
                    'imagen_url' => $producto->imagen_url,
                    'precio_venta' => $precio?->precio_venta,
                    'tiene_descuento' => $precio?->tiene_descuento ?? false,
                    'descuento' => $precio?->descuento ?? 0,
                    'lotes' => $lote ? [[
                        'id' => $lote->id,
                        'cantidad_actual' => (int) $lote->cantidad_actual,
                        'fecha_vencimiento' => $lote->fecha_vencimiento,
                    ]] : [],
                ];
            })
            ->values();
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

            'cliente' => [
                'nullable',
                'array',
            ],

            'cliente.tipo_documento' => [
                'nullable',
                'in:DNI,RUC',
            ],

            'cliente.numero_documento' => [
                'nullable',
                'string',
                'max:20',
            ],

            'cliente.nombre' => [
                'nullable',
                'string',
                'max:255',
            ],

            'cliente.razon_social_nombre' => [
                'nullable',
                'string',
                'max:255',
            ],

            'cliente.ap_paterno' => [
                'nullable',
                'string',
                'max:100',
            ],

            'cliente.ap_materno' => [
                'nullable',
                'string',
                'max:100',
            ],

            'cliente.nombres' => [
                'nullable',
                'string',
                'max:100',
            ],

            'cliente.direccion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'cliente.contacto' => [
                'bail',
                'nullable',
                'string',
                'max:255',
            ],

            'sesion_caja_id' => [
                'required',
                'exists:sesion_cajas,id',
            ],

            'tipo_comprobante_id' => [
                'required',
                Rule::exists('tipo_comprobantes', 'id')->where('activo', true),
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
                Rule::exists('tipo_pagos', 'id')->where('activo', true),
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

        $contacto = trim($validated['cliente']['contacto'] ?? '');
        $datosContacto = [];

        if ($contacto !== '') {
            $campoContacto = str_contains($contacto, '@') ? 'email' : 'telefono';
            $valorContacto = $campoContacto === 'telefono'
                ? str_replace([' ', '-'], '', $contacto)
                : $contacto;
            $reglaContacto = $campoContacto === 'telefono'
                ? 'regex:/^\+?[0-9]{7,15}$/D'
                : 'email';

            $validator = Validator::make(['contacto' => $valorContacto], [
                'contacto' => ['required', $reglaContacto],
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    'cliente.contacto' => 'Ingresa un número de celular o un correo electrónico válido.',
                ]);
            }

            $datosContacto[$campoContacto] = $valorContacto;
        }

        /*
        |--------------------------------------------------------------------------
        | CLIENTE
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

        $venta = DB::transaction(function () use ($validated, $datosContacto) {
            $sesion = SesionCaja::whereKey($validated['sesion_caja_id'])
                ->where('user_id', Auth::id())
                ->where('estado', 'abierta')
                ->lockForUpdate()
                ->first();

            if (!$sesion) {
                throw ValidationException::withMessages([
                    'sesion_caja_id' => 'Tu sesión de caja ya no está abierta. Abre una caja para continuar.',
                ]);
            }

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
            // =====================================================
            // CLIENTE DE LA VENTA
            // =====================================================

            $clienteId = $validated['cliente_id'] ?? null;

            if ($clienteId) {

                // Cliente seleccionado por el vendedor
                $cliente = Cliente::where('id', $clienteId)
                    ->where('activo', true)
                    ->first();

                if (!$cliente) {
                    throw new \Exception(
                        'El cliente seleccionado no existe o se encuentra inactivo.'
                    );
                }

                $clienteId = $cliente->id;
            } elseif (
                !empty($validated['cliente']['numero_documento'])
            ) {

                $datosCliente = $validated['cliente'];

                // Buscar si ya existe por número de documento
                $cliente = Cliente::where(
                    'numero_documento',
                    $datosCliente['numero_documento']
                )->first();

                // Si no existe, crearlo
                if (!$cliente) {

                    $nombreCompleto = trim(
                        (string) ($datosCliente['nombre'] ?? '')
                    );

                    $razonSocialNombre = trim(
                        (string) ($datosCliente['razon_social_nombre'] ?? '')
                    );

                    if ($razonSocialNombre === '') {
                        $razonSocialNombre = $nombreCompleto;
                    }

                    $cliente = Cliente::create([
                        'tipo_documento' =>
                        $datosCliente['tipo_documento'] ?? 'DNI',

                        'numero_documento' =>
                        $datosCliente['numero_documento'],

                        'razon_social_nombre' =>
                        $razonSocialNombre,

                        'ap_paterno' =>
                        $datosCliente['ap_paterno'] ?? '',

                        'ap_materno' =>
                        $datosCliente['ap_materno'] ?? '',

                        'nombres' =>
                        $datosCliente['nombres'] ?? '',

                        'direccion' =>
                        $datosCliente['direccion'] ?? null,

                        'activo' => true,
                    ]);
                }

                $clienteId = $cliente->id;
            } else {

                // Si no se seleccionó cliente, usar/crear CLIENTES VARIOS
                $cliente = Cliente::firstOrCreate(
                    [
                        'numero_documento' => '00000000'
                    ],
                    [
                        'tipo_documento' => 'DNI',
                        'nombre' => 'CLIENTES VARIOS',
                        'razon_social_nombre' => 'CLIENTES VARIOS',
                        'ap_paterno' => '',
                        'ap_materno' => '',
                        'nombres' => 'CLIENTES VARIOS',
                        'activo' => true,
                    ]
                );

                $clienteId = $cliente->id;
            }

            if ($datosContacto && $cliente->numero_documento !== '00000000') {
                $cliente->update($datosContacto);
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
                ->sort()
                ->values();

            $productosVenta = Producto::whereIn(
                'id',
                $productoIds
            )
                ->with('marca:id,empresa_id')
                ->with('marca.empresa:id,razon_social')
                ->orderBy('id')
                ->lockForUpdate()
                ->get([
                    'id',
                    'empresa_id',
                    'marca_id',
                    'nombre',
                    'stock_actual',
                    'activo',
                ])
                ->keyBy('id');

            $preciosVigentes = PrecioProducto::whereIn('producto_id', $productoIds)
                ->where('activo', true)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get()
                ->unique('producto_id')
                ->keyBy('producto_id');

            $loteIds = collect($validated['detalles'])->pluck('lote_id')->unique()->sort()->values();
            $lotesVenta = Lote::with('detalleEntrada')
                ->whereIn('id', $loteIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /*
            |--------------------------------------------------------------------------
            | VALIDAR PRODUCTOS
            |--------------------------------------------------------------------------
            */

            if ($productosVenta->count() !== $productoIds->count() || $lotesVenta->count() !== $loteIds->count()) {
                throw ValidationException::withMessages([
                    'detalles.stock' => 'Uno o más productos o lotes ya no están disponibles.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR EMPRESA
            |--------------------------------------------------------------------------
            |
            | Todos los productos deben tener una empresa.
            |
            */

            /*
            |--------------------------------------------------------------------------
            | VALIDAR MARCA
            |--------------------------------------------------------------------------
            |
            | La marca se obtiene directamente del producto.
            |
            */

            $productoSinMarca = $productosVenta->first(
                fn($producto) => is_null($producto->marca_id) || !$producto->marca
            );

            if ($productoSinMarca) {
                throw new \Exception(
                    "El producto {$productoSinMarca->nombre} no tiene una marca asignada."
                );
            }

            $productoConMarcaSinEmpresa = $productosVenta->first(
                fn($producto) => is_null($producto->marca->empresa_id)
            );

            if ($productoConMarcaSinEmpresa) {
                throw new \Exception(
                    "La marca del producto {$productoConMarcaSinEmpresa->nombre} no tiene una empresa asignada."
                );
            }

            $empresaPorProducto = $productosVenta->mapWithKeys(
                fn($producto) => [
                    $producto->id => $producto->marca->empresa_id,
                ]
            );

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
                ->groupBy(
                    fn($detalle) => $empresaPorProducto[$detalle['producto_id']]
                );

            $ventas = collect();
            $grupoVenta = (string) Str::uuid();
            $fechaEmision = now();


            foreach ($detallesPorEmpresa as $detallesEmpresa) {
                $primerProducto = $productosVenta->firstWhere(
                    'id',
                    $detallesEmpresa->first()['producto_id']
                );

                $empresaEmisoraId = $empresaPorProducto[$primerProducto->id];
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

                DB::table('empresas')
                    ->where('id', $empresaEmisoraId)
                    ->lockForUpdate()
                    ->first();

                $serieComprobante = SerieComprobante::where(
                    'tienda_id',
                    $tienda->id
                )
                    ->where(
                        'tipo_comprobante_id',
                        $validated['tipo_comprobante_id']
                    )
                    ->where(
                        'empresa_id',
                        $empresaEmisoraId
                    )
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first();

                if (!$serieComprobante) {
                    $nombreTipoComprobante = Str::lower(
                        $tipoComprobante->nombre
                    );
                    $seriePorDefecto = Str::contains(
                        $nombreTipoComprobante,
                        ['boleta', 'boleto']
                    )
                        ? 'B001'
                        : (Str::contains($nombreTipoComprobante, 'factura')
                            ? 'F001'
                            : null);

                    if ($seriePorDefecto === null) {
                        $nombreEmpresa =
                            $primerProducto->marca->empresa?->razon_social
                            ?? "ID {$empresaEmisoraId}";

                        throw ValidationException::withMessages([
                            'tipo_comprobante_id' =>
                                "La empresa {$nombreEmpresa} no tiene una serie activa configurada para {$tipoComprobante->nombre}.",
                        ]);
                    }

                    $serieComprobante = SerieComprobante::where(
                        'tienda_id',
                        $tienda->id
                    )
                        ->where('empresa_id', $empresaEmisoraId)
                        ->where(
                            'tipo_comprobante_id',
                            $validated['tipo_comprobante_id']
                        )
                        ->where('serie', $seriePorDefecto)
                        ->lockForUpdate()
                        ->first();

                    if ($serieComprobante) {
                        $serieComprobante->activo = true;
                        $serieComprobante->save();
                    } else {
                        $serieComprobante = SerieComprobante::create([
                            'tienda_id' => $tienda->id,
                            'empresa_id' => $empresaEmisoraId,
                            'tipo_comprobante_id' =>
                                $validated['tipo_comprobante_id'],
                            'serie' => $seriePorDefecto,
                            'correlativo_actual' => 0,
                            'activo' => true,
                        ]);
                    }
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
                    $fechaEmision,

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

                    'grupo_venta' => $grupoVenta,

                    /*
                |--------------------------------------------------------------------------
                | EMPRESA EMISORA DEL COMPROBANTE
                |--------------------------------------------------------------------------
                |
                | Cada venta del grupo contiene productos de una sola empresa.
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

                    $producto = $productosVenta->get($detalle['producto_id']);

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

                    $lote = $lotesVenta->get($detalle['lote_id']);

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
                        throw ValidationException::withMessages([
                            'detalles.stock' => 'El lote seleccionado no pertenece al producto.',
                        ]);
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

                    if (!$producto->activo) {
                        throw ValidationException::withMessages([
                            'detalles.stock' => "El producto {$producto->nombre} ya no está activo.",
                        ]);
                    }

                    if ($lote->fecha_vencimiento && $lote->fecha_vencimiento->toDateString() < now('America/Lima')->toDateString()) {
                        throw ValidationException::withMessages([
                            'detalles.stock' => "El lote de {$producto->nombre} está vencido.",
                        ]);
                    }

                    $disponible = min($producto->stock_actual, $lote->cantidad_actual);

                    if ($disponible < $cantidad) {
                        throw ValidationException::withMessages([
                            'detalles.stock' => "Stock insuficiente para {$producto->nombre}. Disponible en el lote seleccionado: {$disponible}.",
                        ]);
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

                    $precioVigente = $preciosVigentes->get($producto->id);
                    $precioUnitario = (float) ($precioVigente?->precio_venta ?? 0);

                    if ($precioUnitario <= 0 || $precioUnitario !== (float) $detalle['precio_unitario']) {
                        throw ValidationException::withMessages([
                            'detalles.precio' => "El precio de {$producto->nombre} no está disponible o cambió. Vuelve a agregar el producto.",
                        ]);
                    }

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

                    $descuentoPermitido = $precioVigente->tiene_descuento ? (float) $precioVigente->descuento : 0;
                    if ($descuento > min($bruto, $descuentoPermitido)) {
                        throw ValidationException::withMessages([
                            'detalles.precio' => "El descuento de {$producto->nombre} supera el importe permitido.",
                        ]);
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

                        'empresa_id' =>
                        $empresaPorProducto[$producto->id],
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

            if ($ventas->sum('importe_total') <= 0) {
                throw ValidationException::withMessages(['detalles.precio' => 'El total de la venta debe ser mayor a cero.']);
            }

            return $ventas;
        }, 3);

        /*
        |--------------------------------------------------------------------------
        | REDIRECCIÓN AL COMPROBANTE
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->route('ventas.create')
            ->with('comprobantes_emitidos_ids', $venta->pluck('id')->all());
    }

    /**
     * Mostrar venta
     */
    public function show(Venta $venta)
    {
        return redirect()->route('ventas.comprobante', $venta);
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

        $comprobantes = $venta->grupo_venta === null
            ? collect([$venta])
            : Venta::where('grupo_venta', $venta->grupo_venta)
                ->where('activo', true)
                ->with([
                    'cliente',
                    'usuario',
                    'tipoComprobante',
                    'tipoPago',
                    'sesionCaja',
                    'detalles.producto',
                    'detalles.lote',
                    'empresaEmisora',
                    'serieComprobante',
                ])
                ->orderBy('id')
                ->get();

        $tienda = Tienda::first();

        return Inertia::render('Ventas/Comprobante', [
            'venta' => $venta,
            'comprobantes' => $comprobantes,
            'tienda' => $tienda,
        ]);
    }
}
