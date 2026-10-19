<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\TipoPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{

    public function index(Request $request)
    {

        $empresaId = $request->input('empresa_id');
        $tipoPagoId = $request->input('tipo_pago_id');
        $periodoProductos = $request->input('periodo_productos', 'mes');
        $fechaReferencia = $request->input('fecha_referencia', Carbon::today()->toDateString());
        $periodoVentasEmpresa = $request->input('periodo_ventas_empresa') ?: 'mes';
        $fechaReferenciaVentasEmpresa = $request->input(
            'fecha_referencia_ventas_empresa'
        ) ?: Carbon::today()->toDateString();

        $request->validate([
            'periodo_productos' => 'nullable|in:dia,semana,mes',
            'fecha_referencia' => 'nullable|date',
            'periodo_ventas_empresa' => 'nullable|in:dia,semana,mes',
            'fecha_referencia_ventas_empresa' => 'nullable|date',
        ]);

        [$inicioVentasEmpresa, $finVentasEmpresa] = $this->rangoProductos(
            $periodoVentasEmpresa,
            Carbon::parse($fechaReferenciaVentasEmpresa)
        );

        $hoyInicio = Carbon::today();
        $hoyFin = Carbon::today()->endOfDay();

        $semanaInicio = Carbon::now()->startOfWeek();
        $semanaFin = Carbon::now()->endOfWeek();

        $mesInicio = Carbon::now()->startOfMonth();
        $mesFin = Carbon::now()->endOfMonth();


        $ventasQuery = Venta::query()
            ->where('activo', true);

        if ($empresaId) {
            $ventasQuery->where(
                'empresa_emisora_id',
                $empresaId
            );
        }

        if ($tipoPagoId) {
            $ventasQuery->where(
                'tipo_pago_id',
                $tipoPagoId
            );
        }


        $ventasHoy = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $hoyInicio,
                $hoyFin,
            ])
            ->sum('importe_total');

        $ventasSemana = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $semanaInicio,
                $semanaFin,
            ])
            ->sum('importe_total');

        $ventasMes = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $mesInicio,
                $mesFin,
            ])
            ->sum('importe_total');


        $ventasPorPeriodo = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $mesInicio,
                $mesFin,
            ])
            ->select(
                DB::raw('DATE(fecha_emision) as fecha'),
                DB::raw('SUM(importe_total) as total'),
                DB::raw('COUNT(*) as cantidad')
            )
            ->groupBy(
                DB::raw('DATE(fecha_emision)')
            )
            ->orderBy('fecha')
            ->get();

        $maxVentaDia = $ventasPorPeriodo->max('total') ?: 1;

        $ventasPorPeriodo = $ventasPorPeriodo
            ->map(function ($venta) use ($maxVentaDia) {

                return [
                    'fecha' => Carbon::parse($venta->fecha)
                        ->format('d/m/Y'),

                    'total' => round(
                        (float) $venta->total,
                        2
                    ),

                    'cantidad' => (int) $venta->cantidad,

                    'porcentaje' => round(
                        ((float) $venta->total / $maxVentaDia) * 100,
                        2
                    ),
                ];
            })
            ->values();

        $ventasPorEmpresa = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $inicioVentasEmpresa,
                $finVentasEmpresa,
            ])
            ->select(
                'empresa_emisora_id',
                DB::raw('SUM(importe_total) as total'),
                DB::raw('COUNT(*) as cantidad')
            )
            ->with('empresaEmisora')
            ->groupBy('empresa_emisora_id')
            ->get()
            ->map(function ($venta) {

                return [
                    'empresa_id' => $venta->empresa_emisora_id,

                    'empresa' => $venta->empresaEmisora?->razon_social
                        ?? $venta->empresaEmisora?->nombre
                        ?? 'Sin empresa',

                    'cantidad' => (int) $venta->cantidad,

                    'total' => round(
                        (float) $venta->total,
                        2
                    ),
                ];
            })
            ->values();



        $ventasPorTipoPago = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $mesInicio,
                $mesFin,
            ])
            ->select(
                'tipo_pago_id',
                DB::raw('SUM(importe_total) as total'),
                DB::raw('COUNT(*) as cantidad')
            )
            ->with('tipoPago')
            ->groupBy('tipo_pago_id')
            ->get()
            ->map(function ($venta) {

                return [
                    'tipo_pago_id' => $venta->tipo_pago_id,

                    'tipo_pago' => $venta->tipoPago?->nombre
                        ?? 'Sin especificar',

                    'cantidad' => (int) $venta->cantidad,

                    'total' => round(
                        (float) $venta->total,
                        2
                    ),
                ];
            })
            ->values();



        $productosMasVendidos = DB::table('detalle_ventas')
            ->join(
                'ventas',
                'ventas.id',
                '=',
                'detalle_ventas.venta_id'
            )
            ->join(
                'productos',
                'productos.id',
                '=',
                'detalle_ventas.producto_id'
            )
            ->where('ventas.activo', true)
            ->whereBetween('ventas.fecha_emision', [
                $mesInicio,
                $mesFin,
            ])->when(
                $empresaId,
                function ($query) use ($empresaId) {

                    $query->where(
                        'ventas.empresa_emisora_id',
                        $empresaId
                    );
                }
            )
            ->select(
                'productos.id',
                'productos.codigo',
                'productos.nombre',
                DB::raw(
                    'SUM(detalle_ventas.cantidad) as cantidad'
                ),
                DB::raw(
                    'SUM(detalle_ventas.subtotal) as total'
                )
            )
            ->groupBy(
                'productos.id',
                'productos.codigo',
                'productos.nombre'
            )
            ->orderByDesc('cantidad')
            ->limit(10)
            ->get()
            ->map(function ($producto) {

                return [
                    'id' => $producto->id,

                    'codigo' => $producto->codigo,

                    'producto' => $producto->nombre,

                    'cantidad' => (float) $producto->cantidad,

                    'total' => round(
                        (float) $producto->total,
                        2
                    ),
                ];
            })
            ->values();

        $productosVendidosEmpresa = $empresaId
            ? $this->obtenerProductosVendidosPorEmpresa(
                (int) $empresaId,
                $periodoProductos,
                $fechaReferencia
            )
            : collect();

        $stockBajo = Producto::query()
            ->where('activo', true)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orderBy('stock_actual')
            ->limit(15)
            ->get([
                'id',
                'codigo',
                'nombre',
                'stock_actual',
                'stock_minimo',
            ])
            ->map(function ($producto) {
                return [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'producto' => $producto->nombre,
                    'stock_actual' => (float) $producto->stock_actual,
                    'stock_minimo' => (float) $producto->stock_minimo,
                ];
            })
            ->values();

        $hoy = Carbon::today();

        $fechaLimiteVencimiento = Carbon::today()
            ->addDays(30);

        $hoy = Carbon::today();

        $fechaLimiteVencimiento = Carbon::today()
            ->addDays(30);

        // $proximosVencer = Producto::query()
        //     ->where('activo', true)
        //     ->whereNotNull('fecha_vencimiento')
        //     ->whereBetween('fecha_vencimiento', [
        //         $hoy,
        //         $fechaLimiteVencimiento,
        //     ])
        //     ->orderBy('fecha_vencimiento')
        //     ->limit(15)
        //     ->get([
        //         'id',
        //         'codigo',
        //         'nombre',
        //         'stock_actual',
        //         'fecha_vencimiento',
        //     ])
        //     ->map(function ($producto) use ($hoy) {

        //         $fechaVencimiento = Carbon::parse(
        //             $producto->fecha_vencimiento
        //         );


        //     return [
        //         'id' => $producto->id,

        //         'codigo' => $producto->codigo,

        //         'producto' => $producto->nombre,

        //         'stock_actual' => (float) $producto->stock_actual,

        //         'fecha_vencimiento' =>
        //         $fechaVencimiento->format('d/m/Y'),

        //         'dias_restantes' =>
        //         $hoy->diffInDays(
        //             $fechaVencimiento
        //         ),
        //     ];
        // })
        // ->values();

        $proximosVencer = DB::table('lotes')
            ->join(
                'detalle_entradas',
                'detalle_entradas.id',
                '=',
                'lotes.detalle_entrada_id'
            )
            ->join(
                'productos',
                'productos.id',
                '=',
                'detalle_entradas.producto_id'
            )
            ->where('productos.activo', true)
            ->whereNotNull('lotes.fecha_vencimiento')
            ->whereBetween('lotes.fecha_vencimiento', [
                $hoy,
                $fechaLimiteVencimiento,
            ])
            ->where('lotes.cantidad_actual', '>', 0)
            ->orderBy('lotes.fecha_vencimiento')
            ->limit(15)
            ->select(
                'productos.id as producto_id',
                'productos.codigo',
                'productos.nombre',
                'lotes.id as lote_id',
                'lotes.numero_lote',
                'lotes.cantidad_actual',
                'lotes.fecha_vencimiento'
            )
            ->get()
            ->map(function ($lote) use ($hoy) {

                $fechaVencimiento = Carbon::parse(
                    $lote->fecha_vencimiento
                );

                return [
                    'id' => $lote->lote_id,
                    'producto_id' => $lote->producto_id,
                    'codigo' => $lote->codigo,
                    'producto' => $lote->nombre,
                    'lote' => $lote->numero_lote,
                    'stock_actual' => (int) $lote->cantidad_actual,
                    'fecha_vencimiento' => $fechaVencimiento->format('d/m/Y'),
                    'dias_restantes' => $hoy->diffInDays($fechaVencimiento),
                ];
            })
            ->values();

        $empresas = Empresa::query()
            ->orderBy('razon_social')
            ->get([
                'id',
                'razon_social',
            ]);


        $tiposPago = TipoPago::query()
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
            ]);



        return Inertia::render('Reportes/Index', [

            /*
            |--------------------------------------------------------------------------
            | RESUMEN
            |--------------------------------------------------------------------------
            */

            'resumen' => [
                'hoy' => round(
                    (float) $ventasHoy,
                    2
                ),

                'semana' => round(
                    (float) $ventasSemana,
                    2
                ),

                'mes' => round(
                    (float) $ventasMes,
                    2
                ),
            ],


            /*
            |--------------------------------------------------------------------------
            | VENTAS POR DÍA
            |--------------------------------------------------------------------------
            */

            'ventasPorPeriodo' => $ventasPorPeriodo,


            /*
            |--------------------------------------------------------------------------
            | VENTAS POR EMPRESA
            |--------------------------------------------------------------------------
            */

            'ventasPorEmpresa' => $ventasPorEmpresa,


            /*
            |--------------------------------------------------------------------------
            | VENTAS POR TIPO DE PAGO
            |--------------------------------------------------------------------------
            */

            'ventasPorTipoPago' => $ventasPorTipoPago,


            /*
            |--------------------------------------------------------------------------
            | PRODUCTOS MÁS VENDIDOS
            |--------------------------------------------------------------------------
            */

            'productosMasVendidos' => $productosMasVendidos,

            'productosVendidosEmpresa' => $productosVendidosEmpresa,


            /*
            |--------------------------------------------------------------------------
            | STOCK BAJO
            |--------------------------------------------------------------------------
            */

            'stockBajo' => $stockBajo,



            /*
            |--------------------------------------------------------------------------
            | PRÓXIMOS A VENCER
            |--------------------------------------------------------------------------
            */

            'proximosVencer' => $proximosVencer,


            /*
            |--------------------------------------------------------------------------
            | OPCIONES DE FILTROS
            |--------------------------------------------------------------------------
            */

            'empresas' => $empresas,

            'tiposPago' => $tiposPago,


            /*
            |--------------------------------------------------------------------------
            | FILTROS ACTUALES
            |--------------------------------------------------------------------------
            */

            'filtros' => [
                'empresa_id' => $empresaId,
                'tipo_pago_id' => $tipoPagoId,
                'periodo_productos' => $periodoProductos,
                'fecha_referencia' => $fechaReferencia,
                'periodo_ventas_empresa' => $periodoVentasEmpresa,
                'fecha_referencia_ventas_empresa' => $fechaReferenciaVentasEmpresa,
            ]
        ]);
    }

    public function productosEmpresaPdf(Request $request)
    {
        $filtros = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'periodo_productos' => 'required|in:dia,semana,mes',
            'fecha_referencia' => 'required|date',
        ]);

        $empresa = Empresa::findOrFail($filtros['empresa_id']);
        $productos = $this->obtenerProductosVendidosPorEmpresa(
            (int) $empresa->id,
            $filtros['periodo_productos'],
            $filtros['fecha_referencia']
        );

        $periodo = $filtros['periodo_productos'];
        [$inicio, $fin] = $this->rangoProductos(
            $periodo,
            Carbon::parse($filtros['fecha_referencia'])
        );

        return Pdf::loadView('reportes.productos-empresa', [
            'empresa' => $empresa,
            'productos' => $productos,
            'periodo' => ucfirst($periodo),
            'fechaInicio' => $inicio->format('d/m/Y'),
            'fechaFin' => $fin->format('d/m/Y'),
            'totalUnidades' => $productos->sum('cantidad'),
            'totalImporte' => $productos->sum('total'),
        ])->setPaper('a4', 'portrait')->stream(
            'productos-vendidos-' . str($empresa->razon_social)->slug() . '.pdf'
        );
    }

    private function obtenerProductosVendidosPorEmpresa(
        int $empresaId,
        string $periodo,
        string $fechaReferencia
    ) {
        [$inicio, $fin] = $this->rangoProductos(
            $periodo,
            Carbon::parse($fechaReferencia)
        );

        return DB::table('detalle_ventas')
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->join('productos', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->where('detalle_ventas.empresa_id', $empresaId)
            ->where('ventas.activo', true)
            ->whereBetween('ventas.fecha_emision', [$inicio, $fin])
            ->select(
                'productos.codigo',
                'productos.nombre',
                DB::raw('SUM(detalle_ventas.cantidad) as cantidad'),
                DB::raw('SUM(detalle_ventas.subtotal) as total')
            )
            ->groupBy('productos.id', 'productos.codigo', 'productos.nombre')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn ($producto) => [
                'codigo' => $producto->codigo,
                'producto' => $producto->nombre,
                'cantidad' => (int) $producto->cantidad,
                'total' => round((float) $producto->total, 2),
            ])
            ->values();
    }

    private function rangoProductos(string $periodo, Carbon $fechaReferencia): array
    {
        $fechaReferencia = $fechaReferencia->copy();

        return match ($periodo) {
            'dia' => [$fechaReferencia->copy()->startOfDay(), $fechaReferencia->copy()->endOfDay()],
            'semana' => [$fechaReferencia->copy()->startOfWeek(), $fechaReferencia->copy()->endOfWeek()],
            default => [$fechaReferencia->copy()->startOfMonth(), $fechaReferencia->copy()->endOfMonth()],
        };
    }

    /**
     * Reporte de ventas
     */
    public function ventas(Request $request)
    {
        $periodo = $request->input('periodo', 'mes');
        $empresaId = $request->input('empresa_id');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        /*
        |--------------------------------------------------------------------------
        | DETERMINAR RANGO DE FECHAS
        |--------------------------------------------------------------------------
        */

        switch ($periodo) {

            case 'dia':
                $inicio = Carbon::today();
                $fin = Carbon::today()->endOfDay();
                break;

            case 'semana':
                $inicio = Carbon::now()->startOfWeek();
                $fin = Carbon::now()->endOfWeek();
                break;

            case 'mes':
                $inicio = Carbon::now()->startOfMonth();
                $fin = Carbon::now()->endOfMonth();
                break;

            case 'anio':
                $inicio = Carbon::now()->startOfYear();
                $fin = Carbon::now()->endOfYear();
                break;

            case 'personalizado':

                $inicio = $fechaInicio
                    ? Carbon::parse($fechaInicio)->startOfDay()
                    : Carbon::now()->startOfMonth();

                $fin = $fechaFin
                    ? Carbon::parse($fechaFin)->endOfDay()
                    : Carbon::now()->endOfDay();

                break;

            default:

                $periodo = 'mes';

                $inicio = Carbon::now()->startOfMonth();
                $fin = Carbon::now()->endOfMonth();

                break;
        }

        /*
        |--------------------------------------------------------------------------
        | CONSULTA PRINCIPAL
        |--------------------------------------------------------------------------
        */

        $query = Venta::query()
            ->with([
                'cliente',
                'usuario',
                'tipoComprobante',
                'tipoPago',
                'empresaEmisora',
                'tienda',
            ])
            ->where('activo', true)
            ->whereBetween('fecha_emision', [$inicio, $fin]);

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR EMPRESA
        |--------------------------------------------------------------------------
        */

        if ($empresaId) {
            $query->where('empresa_emisora_id', $empresaId);
        }

        /*
        |--------------------------------------------------------------------------
        | VENTAS
        |--------------------------------------------------------------------------
        */

        $ventas = $query
            ->orderByDesc('fecha_emision')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | RESUMEN GENERAL
        |--------------------------------------------------------------------------
        */

        $totalVentas = $ventas->sum('importe_total');

        $cantidadVentas = $ventas->count();

        $ticketPromedio = $cantidadVentas > 0
            ? $totalVentas / $cantidadVentas
            : 0;

        /*
        |--------------------------------------------------------------------------
        | VENTAS POR DÍA
        |--------------------------------------------------------------------------
        */

        $ventasPorDia = $ventas
            ->groupBy(function ($venta) {
                return Carbon::parse($venta->fecha_emision)
                    ->format('Y-m-d');
            })
            ->map(function ($ventasDia, $fecha) {

                return [
                    'fecha' => $fecha,
                    'cantidad' => $ventasDia->count(),
                    'total' => round(
                        $ventasDia->sum('importe_total'),
                        2
                    ),
                ];
            })
            ->sortBy('fecha')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | VENTAS POR EMPRESA
        |--------------------------------------------------------------------------
        */

        $ventasPorEmpresa = $ventas
            ->groupBy('empresa_emisora_id')
            ->map(function ($ventasEmpresa) {

                $empresa = $ventasEmpresa
                    ->first()
                    ->empresaEmisora;

                return [
                    'empresa_id' => $empresa?->id,

                    'empresa' => $empresa?->razon_social
                        ?? $empresa?->nombre
                        ?? 'Sin empresa',

                    'cantidad' => $ventasEmpresa->count(),

                    'total' => round(
                        $ventasEmpresa->sum('importe_total'),
                        2
                    ),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | VENTAS POR TIPO DE PAGO
        |--------------------------------------------------------------------------
        */

        $ventasPorMetodoPago = $ventas
            ->groupBy('tipo_pago_id')
            ->map(function ($ventasPago) {

                $tipoPago = $ventasPago
                    ->first()
                    ->tipoPago;

                return [
                    'tipo_pago_id' => $tipoPago?->id,

                    'tipo_pago' => $tipoPago?->nombre
                        ?? 'Sin especificar',

                    'cantidad' => $ventasPago->count(),

                    'total' => round(
                        $ventasPago->sum('importe_total'),
                        2
                    ),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | VENTAS POR COMPROBANTE
        |--------------------------------------------------------------------------
        */

        $ventasPorComprobante = $ventas
            ->groupBy('tipo_comprobante_id')
            ->map(function ($ventasComprobante) {

                $tipoComprobante = $ventasComprobante
                    ->first()
                    ->tipoComprobante;

                return [
                    'tipo_comprobante_id' => $tipoComprobante?->id,

                    'tipo_comprobante' => $tipoComprobante?->nombre
                        ?? 'Sin especificar',

                    'cantidad' => $ventasComprobante->count(),

                    'total' => round(
                        $ventasComprobante->sum('importe_total'),
                        2
                    ),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | EMPRESAS PARA EL SELECT
        |--------------------------------------------------------------------------
        */

        $empresas = Empresa::query()
            ->orderBy('razon_social')
            ->get([
                'id',
                'razon_social',
            ]);

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA INERTIA
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Reportes/Ventas', [

            'ventas' => $ventas,

            'resumen' => [

                'total_ventas' => round(
                    $totalVentas,
                    2
                ),

                'cantidad_ventas' => $cantidadVentas,

                'ticket_promedio' => round(
                    $ticketPromedio,
                    2
                ),
            ],

            'ventasPorDia' => $ventasPorDia,

            'ventasPorEmpresa' => $ventasPorEmpresa,

            'ventasPorMetodoPago' => $ventasPorMetodoPago,

            'ventasPorComprobante' => $ventasPorComprobante,

            'empresas' => $empresas,

            'filtros' => [

                'periodo' => $periodo,

                'empresa_id' => $empresaId,

                'fecha_inicio' => $inicio->format('Y-m-d'),

                'fecha_fin' => $fin->format('Y-m-d'),
            ],
        ]);
    }

    public function ventasEmpresaPdf(Request $request, Empresa $empresa)
    {
        $filtros = $request->validate([
            'periodo_ventas_empresa' => 'required|in:dia,semana,mes',
            'fecha_referencia_ventas_empresa' => 'required|date',
            'tipo_pago_id' => 'nullable|exists:tipo_pagos,id',
        ]);

        $periodo = $filtros['periodo_ventas_empresa'];
        $fechaReferencia = Carbon::parse(
            $filtros['fecha_referencia_ventas_empresa']
        );
        [$inicio, $fin] = $this->rangoProductos($periodo, $fechaReferencia);

        $ventas = Venta::query()
            ->where('activo', true)
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->when(
                $filtros['tipo_pago_id'] ?? null,
                fn ($query, $tipoPagoId) => $query->where(
                    'tipo_pago_id',
                    $tipoPagoId
                )
            )
            ->whereHas('detalles', function ($query) use ($empresa) {
                $query->where('empresa_id', $empresa->id);
            })
            ->with([
                'cliente',
                'tipoPago',
                'detalles' => function ($query) use ($empresa) {
                    $query->where('empresa_id', $empresa->id)
                        ->with('producto');
                },
            ])
            ->orderBy('fecha_emision')
            ->get()
            ->map(function (Venta $venta) {
                $cliente = $venta->cliente;

                return [
                    'id' => $venta->id,
                    'fecha' => $venta->fecha_emision?->format('d/m/Y') ?? '—',
                    'hora' => $venta->fecha_emision?->format('H:i:s') ?? '—',
                    'fecha_original' => $venta->fecha_emision,
                    'comprobante' => trim(
                        ($venta->serie ?? '') . '-' . ($venta->correlativo ?? ''),
                        '-'
                    ) ?: ('VENTA #' . $venta->id),
                    'cliente' => $cliente?->razon_social_nombre
                        ?? $cliente?->nombre
                        ?? $cliente?->nombres
                        ?? 'CLIENTES VARIOS',
                    'tipo_pago' => $venta->tipoPago?->nombre ?? 'No especificado',
                    'detalles' => $venta->detalles,
                    'total' => $venta->detalles->sum(
                        fn ($detalle) => (float) $detalle->subtotal
                    ),
                ];
            });

        $ventasPorDia = $ventas->groupBy('fecha');
        $totalGeneral = $ventas->sum('total');
        $totalProductos = $ventas
            ->flatMap(fn ($venta) => $venta['detalles'])
            ->sum(fn ($detalle) => (float) $detalle->cantidad);

        return Pdf::loadView('reportes.empresa-ventas', [
            'empresa' => $empresa,
            'ventasPorDia' => $ventasPorDia,
            'cantidadVentas' => $ventas->count(),
            'totalGeneral' => $totalGeneral,
            'totalProductos' => $totalProductos,
            'periodo' => ucfirst($periodo),
            'fechaInicio' => $inicio->format('d/m/Y'),
            'fechaFin' => $fin->format('d/m/Y'),
        ])->setPaper('a4', 'portrait')->stream(
            'reporte-ventas-' . str($empresa->razon_social)->slug() . '-' .
                $periodo . '-' . $fechaReferencia->format('Y-m-d') . '.pdf'
        );
    }
}
