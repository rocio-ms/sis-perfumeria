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
                $mesInicio,
                $mesFin,
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
            ]
        ]);
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
        /*
    |--------------------------------------------------------------------------
    | OBTENER VENTAS DE LA EMPRESA
    |--------------------------------------------------------------------------
    |
    | La empresa se determina desde detalle_ventas.empresa_id.
    |
    */

        $ventas = Venta::query()
            ->where('ventas.activo', true)
            ->whereExists(function ($query) use ($empresa) {
                $query->select(DB::raw(1))
                    ->from('detalle_ventas')
                    ->whereColumn(
                        'detalle_ventas.venta_id',
                        'ventas.id'
                    )
                    ->where(
                        'detalle_ventas.empresa_id',
                        $empresa->id
                    );
            })
            ->with([
                'tipoPago',
            ])
            ->orderBy('ventas.created_at', 'asc')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | OBTENER DETALLE DE CADA VENTA
    |--------------------------------------------------------------------------
    */

        $ventasReporte = [];

        foreach ($ventas as $venta) {

            $detalles = DB::table('detalle_ventas')
                ->join(
                    'productos',
                    'productos.id',
                    '=',
                    'detalle_ventas.producto_id'
                )
                ->where(
                    'detalle_ventas.venta_id',
                    $venta->id
                )
                ->where(
                    'detalle_ventas.empresa_id',
                    $empresa->id
                )
                ->select([
                    'detalle_ventas.id',
                    'detalle_ventas.producto_id',
                    'detalle_ventas.cantidad',
                    'detalle_ventas.precio_unitario',
                    'detalle_ventas.subtotal',

                    'productos.codigo',
                    'productos.nombre',
                ])
                ->get();


            /*
        |--------------------------------------------------------------------------
        | SI ESTA VENTA TIENE PRODUCTOS DE ESTA EMPRESA
        |--------------------------------------------------------------------------
        */

            if ($detalles->isEmpty()) {
                continue;
            }


            /*
        |--------------------------------------------------------------------------
        | TOTAL DE LA VENTA PARA ESTA EMPRESA
        |--------------------------------------------------------------------------
        */

            $totalVenta = $detalles->sum(function ($detalle) {
                return (float) ($detalle->subtotal ?? 0);
            });


            /*
        |--------------------------------------------------------------------------
        | FECHA Y HORA
        |--------------------------------------------------------------------------
        */

            $fecha = optional($venta->created_at)->format('d/m/Y');
            $hora = optional($venta->created_at)->format('H:i:s');


            /*
        |--------------------------------------------------------------------------
        | CLIENTE
        |--------------------------------------------------------------------------
        */

            $cliente = 'CLIENTES VARIOS';

            if (!empty($venta->cliente_nombre)) {
                $cliente = $venta->cliente_nombre;
            } elseif (!empty($venta->cliente_id)) {

                /*
            | Si tu venta tiene relación cliente,
            | puedes reemplazar esto por $venta->cliente->...
            */

                $cliente = 'Cliente registrado';
            }


            /*
        |--------------------------------------------------------------------------
        | COMPROBANTE
        |--------------------------------------------------------------------------
        */

            $comprobante = $venta->numero_comprobante
                ?? $venta->serie_comprobante
                ?? ('VENTA #' . $venta->id);


            /*
        |--------------------------------------------------------------------------
        | TIPO DE PAGO
        |--------------------------------------------------------------------------
        */

            $tipoPago = 'No especificado';

            if ($venta->tipoPago) {
                $tipoPago = $venta->tipoPago->nombre;
            }


            /*
        |--------------------------------------------------------------------------
        | GUARDAR INFORMACIÓN
        |--------------------------------------------------------------------------
        */

            $ventasReporte[] = [
                'id' => $venta->id,

                'fecha' => $fecha,

                'hora' => $hora,

                'fecha_original' => $venta->created_at,

                'comprobante' => $comprobante,

                'cliente' => $cliente,

                'tipo_pago' => $tipoPago,

                'detalles' => $detalles,

                'total' => $totalVenta,
            ];
        }


        /*
    |--------------------------------------------------------------------------
    | AGRUPAR POR DÍA
    |--------------------------------------------------------------------------
    */

        $ventasPorDia = collect($ventasReporte)
            ->groupBy(function ($venta) {
                return $venta['fecha'];
            });


        /*
    |--------------------------------------------------------------------------
    | TOTALES
    |--------------------------------------------------------------------------
    */

        $cantidadVentas = count($ventasReporte);

        $totalGeneral = collect($ventasReporte)
            ->sum('total');

        $totalProductos = collect($ventasReporte)
            ->flatMap(function ($venta) {
                return $venta['detalles'];
            })
            ->sum(function ($detalle) {
                return (float) $detalle->cantidad;
            });


        /*
    |--------------------------------------------------------------------------
    | GENERAR PDF
    |--------------------------------------------------------------------------
    */

        $pdf = Pdf::loadView(
            'reportes.empresa-ventas',
            [
                'empresa' => $empresa,
                'ventasPorDia' => $ventasPorDia,
                'cantidadVentas' => $cantidadVentas,
                'totalGeneral' => $totalGeneral,
                'totalProductos' => $totalProductos,
            ]
        );

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream(
            'reporte-ventas-' .
                str($empresa->razon_social)
                ->slug() .
                '.pdf'
        );
    }
}
