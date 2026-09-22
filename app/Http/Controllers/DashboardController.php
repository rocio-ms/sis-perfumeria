<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Producto;
use App\Models\DetalleVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $dias = (int) $request->input('dias', 7);

        if (!in_array($dias, [7, 15, 30])) {
            $dias = 7;
        }

        $fechaInicio = Carbon::today()->subDays($dias - 1);
        $fechaFin = Carbon::today();

        /*
        |--------------------------------------------------------------------------
        | ESTADÍSTICAS
        |--------------------------------------------------------------------------
        */

        $ventas = Venta::where('activo', true)
            ->whereBetween('fecha_emision', [
                $fechaInicio->startOfDay(),
                $fechaFin->endOfDay()
            ]);

        $estadisticas = [
            'ventas' => (clone $ventas)->count(),

            'ingresos' => (clone $ventas)->sum('importe_total'),

            'productos' => Producto::where('activo', true)->count(),

            'stock_bajo' => Producto::where('activo', true)
                ->where('stock_actual', '<=', 5)
                ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | GRÁFICO DE VENTAS
        |--------------------------------------------------------------------------
        */

        $ventasPorDia = (clone $ventas)
            ->select(
                DB::raw('DATE(fecha_emision) as fecha'),
                DB::raw('COUNT(*) as cantidad')
            )
            ->groupBy(DB::raw('DATE(fecha_emision)'))
            ->orderBy('fecha')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | COMPLETAR DÍAS SIN VENTAS
        |--------------------------------------------------------------------------
        */

        $graficoVentas = collect();

        for ($i = 0; $i < $dias; $i++) {

            $fecha = $fechaInicio->copy()->addDays($i);

            $registro = $ventasPorDia->first(function ($venta) use ($fecha) {
                return Carbon::parse($venta->fecha)->isSameDay($fecha);
            });

            $graficoVentas->push([
                'fecha' => $fecha->format('d/m'),
                'cantidad' => $registro
                    ? (int) $registro->cantidad
                    : 0,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS MÁS VENDIDOS
        |--------------------------------------------------------------------------
        */

        $productosMasVendidos = DetalleVenta::query()
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->join('productos', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->where('ventas.activo', true)
            ->whereBetween('ventas.fecha_emision', [
                $fechaInicio->startOfDay(),
                $fechaFin->endOfDay()
            ])
            ->select(
                'productos.id',
                'productos.nombre',
                DB::raw('SUM(detalle_ventas.cantidad) as cantidad')
            )
            ->groupBy(
                'productos.id',
                'productos.nombre'
            )
            ->orderByDesc('cantidad')
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | ÚLTIMAS VENTAS
        |--------------------------------------------------------------------------
        */

        $ultimasVentas = Venta::with([
                'cliente',
                'tipoComprobante'
            ])
            ->where('activo', true)
            ->orderByDesc('fecha_emision')
            ->limit(5)
            ->get()
            ->map(function ($venta) {

                $cliente = $venta->cliente;

                $nombreCliente = 'Cliente anónimo';

                if ($cliente) {
                    $nombreCliente =
                        $cliente->nombres
                        ?? $cliente->nombre
                        ?? $cliente->razon_social
                        ?? 'Cliente';
                }

                $tipo = $venta->tipoComprobante?->nombre
                    ?? 'Comprobante';

                return [
                    'id' => $venta->id,

                    'comprobante' =>
                        $tipo . ' ' .
                        $venta->serie . '-' .
                        $venta->correlativo,

                    'cliente' => $nombreCliente,

                    'fecha' => Carbon::parse(
                        $venta->fecha_emision
                    )->format('d/m/Y H:i'),

                    'total' => (float) $venta->importe_total,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS CON STOCK BAJO
        |--------------------------------------------------------------------------
        */

        $productosStockBajo = Producto::where('activo', true)
            ->where('stock_actual', '<=', 5)
            ->orderBy('stock_actual')
            ->limit(5)
            ->get([
                'id',
                'nombre',
                'stock_actual'
            ])
            ->map(function ($producto) {

                return [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'stock' => (float) $producto->stock_actual,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | DASHBOARD
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Dashboard', [
            'estadisticas' => $estadisticas,
            'graficoVentas' => $graficoVentas,
            'productosMasVendidos' => $productosMasVendidos,
            'ultimasVentas' => $ultimasVentas,
            'productosStockBajo' => $productosStockBajo,
        ]);
    }
}