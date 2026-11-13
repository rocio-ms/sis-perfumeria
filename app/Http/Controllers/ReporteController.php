<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\TipoPago;
use Carbon\Carbon;
use App\Support\CalendarioComercial;
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
        $periodoProductos = $request->input('periodo_productos') ?: 'mes';
        $fechaReferencia = $request->input('fecha_referencia') ?: Carbon::today(CalendarioComercial::ZONA)->toDateString();
        $periodoMasVendidos = $request->input('periodo_mas_vendidos') ?: 'mes';
        $fechaReferenciaMasVendidos = $request->input(
            'fecha_referencia_mas_vendidos'
        ) ?: Carbon::today(CalendarioComercial::ZONA)->toDateString();
        $periodoVentasEmpresa = $request->input('periodo_ventas_empresa') ?: 'mes';
        $fechaReferenciaVentasEmpresa = $request->input(
            'fecha_referencia_ventas_empresa'
        ) ?: Carbon::today(CalendarioComercial::ZONA)->toDateString();

        $request->validate([
            'periodo_productos' => 'nullable|in:dia,semana,mes',
            'fecha_referencia' => 'nullable|date',
            'periodo_mas_vendidos' => 'nullable|in:dia,semana,mes',
            'fecha_referencia_mas_vendidos' => 'nullable|date',
            'periodo_ventas_empresa' => 'nullable|in:dia,semana,mes',
            'fecha_referencia_ventas_empresa' => 'nullable|date',
        ]);

        [$inicioVentasEmpresa, $finVentasEmpresa] = $this->rangoProductos(
            $periodoVentasEmpresa,
            Carbon::parse($fechaReferenciaVentasEmpresa, CalendarioComercial::ZONA)
        );

        $hoyInicio = Carbon::today(CalendarioComercial::ZONA);
        $hoyFin = Carbon::today(CalendarioComercial::ZONA)->endOfDay();

        $semanaInicio = Carbon::now(CalendarioComercial::ZONA)->startOfWeek();
        $semanaFin = Carbon::now(CalendarioComercial::ZONA)->endOfWeek();

        $mesInicio = Carbon::now(CalendarioComercial::ZONA)->startOfMonth();
        $mesFin = Carbon::now(CalendarioComercial::ZONA)->endOfMonth();


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
                $hoyInicio->copy()->utc(),
                $hoyFin->copy()->utc(),
            ])
            ->sum('importe_total');

        $ventasSemana = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $semanaInicio->copy()->utc(),
                $semanaFin->copy()->utc(),
            ])
            ->sum('importe_total');

        $ventasMes = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $mesInicio->copy()->utc(),
                $mesFin->copy()->utc(),
            ])
            ->sum('importe_total');


        $ventasPorPeriodo = (clone $ventasQuery)
            ->whereBetween('fecha_emision', [
                $mesInicio->copy()->utc(),
                $mesFin->copy()->utc(),
            ])
            ->select(
                DB::raw(CalendarioComercial::fechaLocalDeVentaSql() . ' as fecha'),
                DB::raw('SUM(importe_total) as total'),
                DB::raw('COUNT(*) as cantidad')
            )
            ->groupBy(
                DB::raw(CalendarioComercial::fechaLocalDeVentaSql())
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
                $inicioVentasEmpresa->copy()->utc(),
                $finVentasEmpresa->copy()->utc(),
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
                $mesInicio->copy()->utc(),
                $mesFin->copy()->utc(),
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



        [$inicioMasVendidos, $finMasVendidos] = $this->rangoProductos(
            $periodoMasVendidos,
            Carbon::parse($fechaReferenciaMasVendidos, CalendarioComercial::ZONA)
        );

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
                $inicioMasVendidos->copy()->utc(),
                $finMasVendidos->copy()->utc(),
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
                'productos.stock_actual',
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
                'productos.nombre',
                'productos.stock_actual'
            )
            ->orderByDesc('cantidad')
            ->orderBy('productos.nombre')
            ->orderBy('productos.id')
            ->limit(10)
            ->get()
            ->map(function ($producto) {

                return [
                    'id' => $producto->id,

                    'codigo' => $producto->codigo,

                    'producto' => $producto->nombre,

                    'cantidad' => (float) $producto->cantidad,

                    'stock_actual' => (int) $producto->stock_actual,

                    'total' => round(
                        (float) $producto->total,
                        2
                    ),
                ];
            })
            ->values();

        [$inicioProductos, $finProductos] = $this->rangoProductos(
            $periodoProductos,
            Carbon::parse($fechaReferencia, CalendarioComercial::ZONA)
        );

        $unidadesVendidas = DB::table('detalle_ventas')
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->where('ventas.activo', true)
            ->whereBetween('ventas.fecha_emision', [$inicioProductos->copy()->utc(), $finProductos->copy()->utc()])
            ->when($empresaId, fn ($query) => $query->where('ventas.empresa_emisora_id', $empresaId))
            ->groupBy('detalle_ventas.producto_id')
            ->selectRaw('detalle_ventas.producto_id, SUM(detalle_ventas.cantidad) as cantidad');

        $productosMenosVendidos = DB::table('productos')
            ->leftJoinSub($unidadesVendidas, 'vendidos', 'vendidos.producto_id', '=', 'productos.id')
            ->where('productos.activo', true)
            ->when($empresaId, fn ($query) => $query
                ->leftJoin('marcas', 'marcas.id', '=', 'productos.marca_id')
                ->whereRaw('COALESCE(marcas.empresa_id, productos.empresa_id) = ?', [(int) $empresaId]))
            ->select('productos.id', 'productos.codigo', 'productos.nombre', 'productos.stock_actual')
            ->selectRaw('COALESCE(vendidos.cantidad, 0) as cantidad')
            ->orderBy('cantidad')
            ->orderBy('productos.nombre')
            ->orderBy('productos.id')
            ->limit(10)
            ->get();

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

        $hoy = Carbon::today(CalendarioComercial::ZONA);
        $fechaLimiteVencimiento = $hoy->copy()->addDays(30);

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
                $hoy->toDateString(),
                $fechaLimiteVencimiento->toDateString(),
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
                    $lote->fecha_vencimiento,
                    CalendarioComercial::ZONA
                )->startOfDay();

                return [
                    'id' => $lote->lote_id,
                    'producto_id' => $lote->producto_id,
                    'codigo' => $lote->codigo,
                    'producto' => $lote->nombre,
                    'lote' => $lote->numero_lote,
                    'stock_actual' => (int) $lote->cantidad_actual,
                    'fecha_vencimiento' => $fechaVencimiento->format('m/Y'),
                    'dias_restantes' => (int) $hoy->diffInDays($fechaVencimiento),
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

            'productosMenosVendidos' => $productosMenosVendidos,

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
                'periodo_mas_vendidos' => $periodoMasVendidos,
                'fecha_referencia_mas_vendidos' => $fechaReferenciaMasVendidos,
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
            Carbon::parse($filtros['fecha_referencia'], CalendarioComercial::ZONA)
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
            Carbon::parse($fechaReferencia, CalendarioComercial::ZONA)
        );

        return DB::table('detalle_ventas')
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->join('productos', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->where('detalle_ventas.empresa_id', $empresaId)
            ->where('ventas.activo', true)
            ->whereBetween('ventas.fecha_emision', [$inicio->copy()->utc(), $fin->copy()->utc()])
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
                $inicio = Carbon::today(CalendarioComercial::ZONA);
                $fin = Carbon::today(CalendarioComercial::ZONA)->endOfDay();
                break;

            case 'semana':
                $inicio = Carbon::now(CalendarioComercial::ZONA)->startOfWeek();
                $fin = Carbon::now(CalendarioComercial::ZONA)->endOfWeek();
                break;

            case 'mes':
                $inicio = Carbon::now(CalendarioComercial::ZONA)->startOfMonth();
                $fin = Carbon::now(CalendarioComercial::ZONA)->endOfMonth();
                break;

            case 'anio':
                $inicio = Carbon::now(CalendarioComercial::ZONA)->startOfYear();
                $fin = Carbon::now(CalendarioComercial::ZONA)->endOfYear();
                break;

            case 'personalizado':

                $inicio = $fechaInicio
                    ? Carbon::parse($fechaInicio, CalendarioComercial::ZONA)->startOfDay()
                    : Carbon::now(CalendarioComercial::ZONA)->startOfMonth();

                $fin = $fechaFin
                    ? Carbon::parse($fechaFin, CalendarioComercial::ZONA)->endOfDay()
                    : Carbon::now(CalendarioComercial::ZONA)->endOfDay();

                break;

            default:

                $periodo = 'mes';

                $inicio = Carbon::now(CalendarioComercial::ZONA)->startOfMonth();
                $fin = Carbon::now(CalendarioComercial::ZONA)->endOfMonth();

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
            ->whereBetween('fecha_emision', [$inicio->copy()->utc(), $fin->copy()->utc()]);

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
                return Carbon::parse($venta->fecha_emision)->setTimezone(CalendarioComercial::ZONA)
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
            $filtros['fecha_referencia_ventas_empresa'], CalendarioComercial::ZONA
        );
        [$inicio, $fin] = $this->rangoProductos($periodo, $fechaReferencia);

        $ventas = Venta::query()
            ->where('activo', true)
            ->whereBetween('fecha_emision', [$inicio->copy()->utc(), $fin->copy()->utc()])
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
                    'fecha' => $venta->fecha_emision?->copy()->setTimezone(CalendarioComercial::ZONA)->format('d/m/Y') ?? '—',
                    'hora' => $venta->fecha_emision?->copy()->setTimezone(CalendarioComercial::ZONA)->format('H:i:s') ?? '—',
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
