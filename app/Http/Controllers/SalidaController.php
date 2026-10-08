<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Salida;
use App\Models\Kardex;
use App\Models\TipoMovimiento;
use App\Models\DetalleSalida;
use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalidaController extends Controller
{
    /**
     * Mostrar listado de salidas.
     */
    public function index(Request $request)
    {

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
                    ->where(function ($query) use ($buscarProducto) {

                        $query->where('codigo_barras', 'like', "{$buscarProducto}%")
                            ->orWhere('codigo', 'like', "{$buscarProducto}%")
                            ->orWhere('nombre', 'like', "{$buscarProducto}%");
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


        // Salidas registradas
        $salidas = Salida::with([
            'cliente',
            'usuario',
            'detalles.producto',
            'detalles.lote',
        ])
            ->orderByDesc('id')
            ->paginate(10);

        // Productos
        $productos = collect();

        // Clientes activos
        $clientes = Cliente::where('activo', true)
            ->orderBy('numero_documento')
            ->get();

        return Inertia::render('Salidas/Index', [
            'salidas' => $salidas,
            'productos' => $productos,
            'clientes' => $clientes,
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

            $total = 0;

            foreach ($validated['detalles'] as $detalle) {
                $subtotal =
                    $detalle['cantidad'] *
                    $detalle['costo_unitario'];

                $total += $subtotal;
            }

            //Generar código de salida

            $ultimaSalida = Salida::orderByDesc('id')->first();

            $siguienteNumero = $ultimaSalida
                ? $ultimaSalida->id + 1
                : 1;

            $codigoSalida = 'SAL-' . str_pad(
                $siguienteNumero,
                6,
                '0',
                STR_PAD_LEFT
            );

            $salida = Salida::create([
                'codigo_salida' => $codigoSalida,
                'tipo_salida' => $validated['tipo_salida'],
                'fecha_salida' => $validated['fecha_salida'],
                'cliente_id' => $validated['cliente_id'] ?? null,
                'observacion' => $validated['observacion'] ?? null,
                'total' => $total,
                'user_id' => $request->user()->id,
            ]);

            $tipoMovimiento = TipoMovimiento::where('tipo', 'salida')->firstOrFail();

            foreach ($validated['detalles'] as $detalle) {

                $lote = Lote::findOrFail($detalle['lote_id']);

                $producto = Producto::findOrFail(
                    $detalle['producto_id']
                );

                // Validar stock del lote
                if ($lote->cantidad_actual < $detalle['cantidad']) {
                    throw new \Exception(
                        "El lote {$lote->numero_lote} no tiene suficiente stock."
                    );
                }

                // Validar stock del producto
                if ($producto->stock_actual < $detalle['cantidad']) {
                    throw new \Exception(
                        "El producto {$producto->nombre} no tiene suficiente stock."
                    );
                }

                $stockAnterior = $producto->stock_actual;
                $subtotal = $detalle['cantidad'] * $detalle['costo_unitario'];

                // Crear detalle
                DetalleSalida::create([
                    'salida_id' => $salida->id,
                    'producto_id' => $producto->id,
                    'lote_id' => $lote->id,
                    'cantidad' => $detalle['cantidad'],
                    'costo_unitario' => $detalle['costo_unitario'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $subtotal,
                ]);

                // Descontar lote
                $lote->decrement(
                    'cantidad_actual',
                    $detalle['cantidad']
                );

                // Descontar stock general
                $producto->decrement(
                    'stock_actual',
                    $detalle['cantidad']
                );

                $stockNuevo =
                    $stockAnterior - $detalle['cantidad'];

                // Registrar Kardex
                Kardex::create([
                    'producto_id' => $producto->id,
                    'lote_id' => $lote->id,
                    'tipo_movimiento_id' => $tipoMovimiento->id,
                    'entrada_id' => null,
                    'salida_id' => $salida->id,
                    'venta_id' => null,
                    'user_id' => $request->user()->id,
                    'fecha_movimiento' => $validated['fecha_salida'],
                    'cantidad' => $detalle['cantidad'],
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'saldo_cantidad' => $stockNuevo,
                    'costo_unitario' => $detalle['costo_unitario'],
                    'costo_total' => $detalle['cantidad'] * $detalle['costo_unitario'],
                    'saldo_costo_unitario' => $detalle['costo_unitario'],
                ]);
            }
        });

        return redirect()
            ->route('salidas.index')
            ->with('success', 'Salida registrada correctamente.');
    }
}
