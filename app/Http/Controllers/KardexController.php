<?php

namespace App\Http\Controllers;

use App\Models\Kardex;
use App\Models\Producto;
use App\Models\TipoMovimiento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KardexController extends Controller
{
    /**
     * Mostrar el Kardex.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'tipo_movimiento' => ['nullable', Rule::in(['Entrada', 'Salida'])],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
            'sort' => [
                'nullable',
                Rule::in([
                    'id', 'fecha_movimiento', 'tipo_movimiento.nombre',
                    'producto.nombre', 'lote_id', 'cantidad', 'costo_unitario',
                    'costo_total', 'saldo_cantidad', 'saldo_costo_unitario',
                    'observacion',
                ]),
            ],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

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

        if (!empty($validated['producto_id'])) {
            $query->where('producto_id', $validated['producto_id']);
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

        if (!empty($validated['tipo_movimiento'])) {
            $query->whereHas('tipoMovimiento', fn ($tipoQuery) => $tipoQuery
                ->where('nombre', $validated['tipo_movimiento']));
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR FECHA DESDE
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['fecha_desde'])) {
            $query->whereDate('fecha_movimiento', '>=', $validated['fecha_desde']);
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO POR FECHA HASTA
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['fecha_hasta'])) {
            $query->whereDate('fecha_movimiento', '<=', $validated['fecha_hasta']);
        }

        $search = trim($validated['search'] ?? '');
        if ($search !== '') {
            $termino = "%{$search}%";
            $query->where(function ($searchQuery) use ($termino) {
                $searchQuery->where('observacion', 'like', $termino)
                    ->orWhereHas('producto', fn ($productoQuery) => $productoQuery
                        ->where('nombre', 'like', $termino)
                        ->orWhere('codigo', 'like', $termino))
                    ->orWhereHas('lote', fn ($loteQuery) => $loteQuery
                        ->where('numero_lote', 'like', $termino))
                    ->orWhereHas('tipoMovimiento', fn ($tipoQuery) => $tipoQuery
                        ->where('nombre', 'like', $termino));
            });
        }

        $sort = $validated['sort'] ?? 'fecha_movimiento';
        $direction = $validated['direction'] ?? 'desc';

        if ($sort === 'tipo_movimiento.nombre') {
            $query->orderBy(
                TipoMovimiento::select('nombre')
                    ->whereColumn('tipo_movimientos.id', 'kardexs.tipo_movimiento_id'),
                $direction,
            );
        } elseif ($sort === 'producto.nombre') {
            $query->orderBy(
                Producto::select('nombre')
                    ->whereColumn('productos.id', 'kardexs.producto_id'),
                $direction,
            );
        } else {
            $sortColumn = in_array($sort, [
                'fecha_movimiento', 'lote_id', 'cantidad', 'costo_unitario',
                'costo_total', 'saldo_cantidad', 'saldo_costo_unitario',
                'observacion',
            ], true) ? $sort : 'id';
            $query->orderBy($sortColumn, $direction);
        }

        $query->orderBy('id', 'desc');

        /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

        $kardexs = $query->paginate($validated['per_page'] ?? 20)->withQueryString();

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
                'producto.nombre' => $validated['producto_id'] ?? '',
                'tipo_movimiento.nombre' => $validated['tipo_movimiento'] ?? '',
                'fecha_movimiento' => [
                    $validated['fecha_desde'] ?? '',
                    $validated['fecha_hasta'] ?? '',
                ],
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }
}