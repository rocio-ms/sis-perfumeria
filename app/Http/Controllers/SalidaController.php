<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Salida;
use App\Models\Kardex;
use App\Models\Tienda;
use App\Models\TipoMovimiento;
use App\Models\DetalleSalida;
use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SalidaController extends Controller
{
    /**
     * Mostrar listado de salidas.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'tipo_salida' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
            'sort' => [
                'nullable',
                Rule::in([
                    'id',
                    'codigo_salida',
                    'fecha_salida',
                    'tipo_salida',
                    'cliente.nombre_completo',
                    'total',
                ]),
            ],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $buscarProducto = trim($request->input('buscar_producto', ''));

        // Búsqueda rápida de productos
        if ($request->has('buscar_producto')) {

            $productos = collect();

            if ($buscarProducto !== '') {

                $productos = Producto::with([
                    'lotes' => function ($query) {
                        $query
                            ->where('cantidad_actual', '>', 0)
                            ->with('detalleEntrada')
                            ->orderBy('id');
                    },
                ])
                    ->where('activo', true)
                    ->where('stock_actual', '>=', 1)
                    ->where(function ($query) use ($buscarProducto) {

                        $query->where('codigo_barras', 'like', "{$buscarProducto}%")
                            ->orWhere('codigo', 'like', "{$buscarProducto}%")
                            ->orWhere('nombre', 'like', "{$buscarProducto}%")
                            ->orWhereHas('lotes', function ($loteQuery) use ($buscarProducto) {
                                $loteQuery
                                    ->where('codigo_barras', 'like', "{$buscarProducto}%")
                                    ->where('cantidad_actual', '>', 0);
                            });
                    })
                    ->orderBy('nombre')
                    ->limit(20)
                    ->get([
                        'id',
                        'codigo',
                        'codigo_barras',
                        'nombre',
                        'stock_actual',
                    ]);
            }

            return Inertia::render('Salidas/Index', [
                'productos' => $productos,
            ]);
        }


        $search = trim($validated['search'] ?? '');
        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';

        // Salidas registradas
        $salidasQuery = Salida::with([
            'cliente',
            'usuario',
            'detalles.producto',
            'detalles.lote',
            'venta.cliente',
            'venta.usuario',
            'venta.tipoComprobante',
            'venta.tipoPago',
            'venta.empresaEmisora',
            'venta.detalles.producto',
            'venta.comprobantes.cliente',
            'venta.comprobantes.usuario',
            'venta.comprobantes.tipoComprobante',
            'venta.comprobantes.tipoPago',
            'venta.comprobantes.empresaEmisora',
            'venta.comprobantes.detalles.producto',
        ]);

        if ($search !== '') {
            $termino = "%{$search}%";
            $salidasQuery->where(function ($query) use ($termino) {
                $query->where('codigo_salida', 'like', $termino)
                    ->orWhere('tipo_salida', 'like', $termino)
                    ->orWhereHas('cliente', fn ($clienteQuery) => $clienteQuery
                        ->where('razon_social_nombre', 'like', $termino)
                        ->orWhere('nombres', 'like', $termino)
                        ->orWhere('ap_paterno', 'like', $termino)
                        ->orWhere('ap_materno', 'like', $termino));
            });
        }

        if (!empty($validated['tipo_salida'])) {
            $salidasQuery->where('tipo_salida', 'like', '%' . $validated['tipo_salida'] . '%');
        }

        if (!empty($validated['fecha_desde'])) {
            $salidasQuery->whereDate('fecha_salida', '>=', $validated['fecha_desde']);
        }

        if (!empty($validated['fecha_hasta'])) {
            $salidasQuery->whereDate('fecha_salida', '<=', $validated['fecha_hasta']);
        }

        if ($sort === 'cliente.nombre_completo') {
            $salidasQuery->orderBy(
                Cliente::selectRaw(
                    "CASE WHEN tipo_documento = 'RUC' THEN razon_social_nombre ELSE nombres END"
                )
                    ->whereColumn('clientes.id', 'salidas.cliente_id'),
                $direction,
            );
        } else {
            $sortColumn = in_array($sort, [
                'codigo_salida',
                'fecha_salida',
                'tipo_salida',
                'total',
            ], true) ? $sort : 'id';
            $salidasQuery->orderBy($sortColumn, $direction);
        }

        $salidas = $salidasQuery
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        // Productos
        $productos = collect();

        return Inertia::render('Salidas/Index', [
            'salidas' => $salidas,
            'productos' => $productos,
            'tienda' => Tienda::first(),
            'filtros' => [
                'search' => $search,
                'fecha_salida' => [
                    $validated['fecha_desde'] ?? '',
                    $validated['fecha_hasta'] ?? '',
                ],
                'tipo_salida' => $validated['tipo_salida'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Registrar una nueva salida.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_salida' => [
                'required',
                'in:venta,consumo,merma,devolucion',
            ],

            'fecha_salida' => [
                'required',
                'date',
            ],

            'cliente_id' => [
                'nullable',
                'exists:clientes,id',
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

            'detalles.*.lote_id' => [
                'required',
                'exists:lotes,id',
            ],

            'detalles.*.cantidad' => [
                'required',
                'integer',
                'min:1',
            ],

            'detalles.*.costo_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],

            'detalles.*.precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $detalles = collect($validated['detalles']);
            $productoIds = $detalles->pluck('producto_id')->unique()->sort()->values();
            $loteIds = $detalles->pluck('lote_id')->unique()->sort()->values();

            $productos = Producto::whereIn('id', $productoIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lotes = Lote::with('detalleEntrada')
                ->whereIn('id', $loteIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($productos->count() !== $productoIds->count() || $lotes->count() !== $loteIds->count()) {
                throw ValidationException::withMessages([
                    'detalles' => 'Uno o más productos o lotes ya no están disponibles. Actualiza la página e inténtalo nuevamente.',
                ]);
            }

            $cantidadesPorProducto = $detalles->groupBy('producto_id')
                ->map(fn ($items) => $items->sum('cantidad'));
            $cantidadesPorLote = $detalles->groupBy('lote_id')
                ->map(fn ($items) => $items->sum('cantidad'));

            foreach ($cantidadesPorProducto as $productoId => $cantidadSolicitada) {
                $producto = $productos->get($productoId);

                if (!$producto->activo) {
                    throw ValidationException::withMessages([
                        'detalles' => "El producto {$producto->nombre} está inactivo y no puede salir del inventario.",
                    ]);
                }

                if ($producto->stock_actual < $cantidadSolicitada) {
                    throw ValidationException::withMessages([
                        'detalles' => "Stock insuficiente para {$producto->nombre}. Disponible: {$producto->stock_actual}.",
                    ]);
                }
            }

            foreach ($detalles as $detalle) {
                $lote = $lotes->get($detalle['lote_id']);
                $detalleEntrada = $lote->detalleEntrada;

                if (!$detalleEntrada || (int) $detalleEntrada->producto_id !== (int) $detalle['producto_id']) {
                    throw ValidationException::withMessages([
                        'detalles' => 'Uno de los lotes seleccionados no pertenece al producto indicado.',
                    ]);
                }
            }

            foreach ($cantidadesPorLote as $loteId => $cantidadSolicitada) {
                $lote = $lotes->get($loteId);
                if ($lote->cantidad_actual < $cantidadSolicitada) {
                    throw ValidationException::withMessages([
                        'detalles' => "Stock insuficiente en el lote {$lote->numero_lote}. Disponible: {$lote->cantidad_actual}.",
                    ]);
                }
            }

            $tipoMovimiento = TipoMovimiento::where('tipo', 'salida')->first();
            if (!$tipoMovimiento) {
                throw ValidationException::withMessages([
                    'detalles' => 'No está configurado el tipo de movimiento de salida.',
                ]);
            }

            $salida = Salida::create([
                'codigo_salida' => 'T-' . Str::random(16),
                'tipo_salida' => $validated['tipo_salida'],
                'fecha_salida' => $validated['fecha_salida'],
                'cliente_id' => $validated['cliente_id'] ?? null,
                'observacion' => $validated['observacion'] ?? null,
                'total' => 0,
                'user_id' => $request->user()->id,
            ]);

            $salida->update([
                'codigo_salida' => 'SAL-' . str_pad(
                    $salida->id,
                    6,
                    '0',
                    STR_PAD_LEFT,
                ),
            ]);

            $total = 0;

            foreach ($detalles as $detalle) {
                $lote = $lotes->get($detalle['lote_id']);
                $producto = $productos->get($detalle['producto_id']);
                $cantidad = (int) $detalle['cantidad'];
                $costoUnitario = (float) $lote->detalleEntrada->precio_compra;
                $precioUnitario = (float) $lote->detalleEntrada->precio_venta;
                $subtotal = round($cantidad * $costoUnitario, 2);
                $total = round($total + $subtotal, 2);

                DetalleSalida::create([
                    'salida_id' => $salida->id,
                    'producto_id' => $producto->id,
                    'lote_id' => $lote->id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                ]);

                $lote->decrement('cantidad_actual', $cantidad);
                $producto->decrement('stock_actual', $cantidad);

                Kardex::create([
                    'producto_id' => $producto->id,
                    'lote_id' => $lote->id,
                    'tipo_movimiento_id' => $tipoMovimiento->id,
                    'salida_id' => $salida->id,
                    'user_id' => $request->user()->id,
                    'fecha_movimiento' => $validated['fecha_salida'],
                    'cantidad' => -$cantidad,
                    'observacion' => 'Salida ' . $salida->codigo_salida,
                    'saldo_cantidad' => $producto->stock_actual,
                    'costo_unitario' => $costoUnitario,
                    'costo_total' => $subtotal,
                    'saldo_costo_unitario' => $costoUnitario,
                ]);
            }

            $salida->update(['total' => $total]);
        });

        return redirect()
            ->route('salidas.index')
            ->with('success', 'Salida registrada correctamente.');
    }
}
