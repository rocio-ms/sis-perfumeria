<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;

class LoteController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'fecha_ingreso_desde' => ['nullable', 'date'],
            'fecha_ingreso_hasta' => ['nullable', 'date'],
            'fecha_vencimiento_desde' => ['nullable', 'date'],
            'fecha_vencimiento_hasta' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
            'sort' => [
                'nullable',
                Rule::in([
                    'id', 'numero_lote', 'detalle_entrada.producto.nombre',
                    'detalle_entrada.producto.codigo',
                    'detalle_entrada.entrada.proveedor.nombre_comercial',
                    'codigo_barras',
                    'fecha_ingreso', 'fecha_vencimiento', 'cantidad_inicial',
                    'cantidad_actual',
                ]),
            ],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Lote::query()->with([
            'detalleEntrada:id,entrada_id,producto_id',
            'detalleEntrada.producto:id,nombre,codigo',
            'detalleEntrada.entrada:id,proveedor_id',
            'detalleEntrada.entrada.proveedor:id,nombre_comercial',
        ]);

        $search = trim($validated['search'] ?? '');
        if ($search !== '') {
            $termino = "%{$search}%";
            $query->where(function ($searchQuery) use ($termino) {
                $searchQuery->where('numero_lote', 'like', $termino)
                    ->orWhere('codigo_barras', 'like', $termino)
                    ->orWhereHas('detalleEntrada.producto', fn ($productoQuery) => $productoQuery
                        ->where('nombre', 'like', $termino)
                        ->orWhere('codigo', 'like', $termino))
                    ->orWhereHas('detalleEntrada.entrada.proveedor', fn ($proveedorQuery) => $proveedorQuery
                        ->where('nombre_comercial', 'like', $termino));
            });
        }

        foreach ([
            'fecha_ingreso' => ['fecha_ingreso_desde', 'fecha_ingreso_hasta'],
            'fecha_vencimiento' => ['fecha_vencimiento_desde', 'fecha_vencimiento_hasta'],
        ] as $column => [$desde, $hasta]) {
            if (!empty($validated[$desde])) {
                $query->whereDate($column, '>=', $validated[$desde]);
            }

            if (!empty($validated[$hasta])) {
                $query->whereDate($column, '<=', $validated[$hasta]);
            }
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        if ($sort === 'detalle_entrada.producto.nombre' || $sort === 'detalle_entrada.producto.codigo') {
            $productColumn = $sort === 'detalle_entrada.producto.nombre' ? 'nombre' : 'codigo';
            $query->orderBy(
                Producto::select($productColumn)
                    ->join('detalle_entradas', 'detalle_entradas.producto_id', '=', 'productos.id')
                    ->whereColumn('detalle_entradas.id', 'lotes.detalle_entrada_id'),
                $direction,
            );
        } elseif ($sort === 'detalle_entrada.entrada.proveedor.nombre_comercial') {
            $query->orderBy(
                Proveedor::select('nombre_comercial')
                    ->join('entradas', 'entradas.proveedor_id', '=', 'proveedors.id')
                    ->join('detalle_entradas', 'detalle_entradas.entrada_id', '=', 'entradas.id')
                    ->whereColumn('detalle_entradas.id', 'lotes.detalle_entrada_id'),
                $direction,
            );
        } else {
            $sortColumn = in_array($sort, [
                'numero_lote', 'codigo_barras', 'fecha_ingreso', 'fecha_vencimiento',
                'cantidad_inicial', 'cantidad_actual',
            ], true) ? $sort : 'id';
            $query->orderBy($sortColumn, $direction);
        }

        $lotes = $query->orderBy('id', 'desc')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return Inertia::render(
            'Lotes/Index',
            [
                'lotes' => $lotes,
                'filtros' => [
                    'search' => $search,
                    'fecha_ingreso' => [
                        $validated['fecha_ingreso_desde'] ?? '',
                        $validated['fecha_ingreso_hasta'] ?? '',
                    ],
                    'fecha_vencimiento' => [
                        $validated['fecha_vencimiento_desde'] ?? '',
                        $validated['fecha_vencimiento_hasta'] ?? '',
                    ],
                    'sort' => $sort,
                    'direction' => $direction,
                ],
            ]
        );
    }
}
