<?php

namespace App\Http\Controllers;

use App\Models\Kardex;
use App\Models\Producto;
use App\Models\TipoMovimiento;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KardexController extends Controller
{
    /**
     * Mostrar el Kardex.
     */
    public function index(Request $request)
    {
        $query = Kardex::with([
            'producto:id,codigo,nombre',
            'lote:id,numero_lote',
            'tipoMovimiento:id,nombre',
            'user:id,name',
            'venta:id,serie,correlativo',
            'entrada:id',
            'salida:id,codigo_salida,tipo_salida',
        ]);

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR PRODUCTO
        |--------------------------------------------------------------------------
        */

        if ($request->filled('producto_id')) {
            $query->where(
                'producto_id',
                $request->producto_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR TIPO DE MOVIMIENTO
        |--------------------------------------------------------------------------
        |
        | Solamente:
        | - Entrada
        | - Salida
        |
        */

        if ($request->filled('tipo_movimiento_id')) {
            $query->where(
                'tipo_movimiento_id',
                $request->tipo_movimiento_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR FECHA DESDE
        |--------------------------------------------------------------------------
        */

        if ($request->filled('fecha_desde')) {
            $query->whereDate(
                'fecha_movimiento',
                '>=',
                $request->fecha_desde
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR FECHA HASTA
        |--------------------------------------------------------------------------
        */

        if ($request->filled('fecha_hasta')) {
            $query->whereDate(
                'fecha_movimiento',
                '<=',
                $request->fecha_hasta
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ORDEN DEL KARDEX
        |--------------------------------------------------------------------------
        |
        | Primero por fecha y luego por ID para mantener
        | el orden exacto de los movimientos.
        |
        */

        $query->orderBy('fecha_movimiento', 'desc')
              ->orderBy('id', 'desc');

        /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

        $kardexs = $query
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS PARA EL FILTRO
        |--------------------------------------------------------------------------
        */

        $productos = Producto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get([
                'id',
                'codigo',
                'nombre',
            ]);

        /*
        |--------------------------------------------------------------------------
        | TIPOS DE MOVIMIENTO
        |--------------------------------------------------------------------------
        |
        | Solo queremos Entrada y Salida.
        |
        */

        $tiposMovimiento = TipoMovimiento::query()
            ->whereIn('nombre', [
                'Entrada',
                'Salida',
            ])
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
            ]);

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA INERTIA
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Kardex/Index', [
            'kardexs' => $kardexs,

            'productos' => $productos,

            'tiposMovimiento' => $tiposMovimiento,

            'filtros' => [
                'producto_id' => $request->producto_id,
                'tipo_movimiento_id' => $request->tipo_movimiento_id,
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
            ],
        ]);
    }
}