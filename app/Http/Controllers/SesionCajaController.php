<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\SesionCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SesionCajaController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $sesiones = SesionCaja::with([
            'caja',
            'usuario',
            'ventas' => function ($query) {
                $query
                    ->where('activo', true)
                    ->with([
                        'cliente',
                        'tipoPago',
                        'tipoComprobante',
                    ])
                    ->orderByDesc('fecha_emision');
            },
        ])
            ->when($search, function ($query, $search) {
                $query->whereHas('caja', function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('fecha_apertura')
            ->paginate(10)
            ->withQueryString();

        $sesiones->getCollection()->transform(function ($sesion) {

            // Las ventas ya vienen filtradas por activo = true
            $ventasActivas = $sesion->ventas;

            $sesion->ventas_efectivo = $ventasActivas
                ->filter(function ($venta) {
                    return strtolower(trim(
                        $venta->tipoPago?->nombre ?? ''
                    )) === 'efectivo';
                })
                ->sum('importe_total');

            $sesion->ventas_yape = $ventasActivas
                ->filter(function ($venta) {
                    $nombre = strtolower(trim(
                        $venta->tipoPago?->nombre ?? ''
                    ));

                    return in_array($nombre, [
                        'yape',
                        'plin',
                        'yape/plin',
                        'yape - plin',
                        'yape / plin',
                    ]);
                })
                ->sum('importe_total');

            $sesion->ventas_tarjeta = $ventasActivas
                ->filter(function ($venta) {
                    return strtolower(trim(
                        $venta->tipoPago?->nombre ?? ''
                    )) === 'tarjeta';
                })
                ->sum('importe_total');

            $sesion->ventas_total =
                $sesion->ventas_efectivo +
                $sesion->ventas_yape +
                $sesion->ventas_tarjeta;

            $sesion->efectivo_esperado =
                (float) $sesion->monto_apertura +
                (float) $sesion->ventas_efectivo;

            // NO eliminar $sesion->ventas

            return $sesion;
        });

        $cajasDisponibles = Caja::where('activo', true)
            ->whereDoesntHave('sesiones', function ($query) {
                $query->where('estado', 'abierta');
            })
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Cajas/Sesiones/Index', [
            'sesiones' => $sesiones,
            'cajasDisponibles' => $cajasDisponibles,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }


    public function abrir(Request $request)
    {
        $validated = $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'monto_apertura' => 'required|numeric|min:0',
        ]);

        $sesionExistente = SesionCaja::where('user_id', Auth::id())
            ->where('estado', 'abierta')
            ->exists();

        if ($sesionExistente) {
            return back()->withErrors([
                'caja_id' => 'Ya tienes una caja abierta. Por favor, cierra la sesión actual antes de abrir una nueva.',
            ]);
        }

        $cajaOcupada = SesionCaja::where('caja_id', $validated['caja_id'])
            ->where('estado', 'abierta')
            ->exists();

        if ($cajaOcupada) {
            return back()->withErrors([
                'caja_id' => 'Esta caja ya tiene una sesión abierta. Por favor, selecciona otra caja.',
            ]);
        }

        $sesion = SesionCaja::create([
            'fecha_apertura' => now(),
            'fecha_cierre' => null,

            'monto_apertura' => $validated['monto_apertura'],

            'monto_cierre_teorico' => 0,
            'monto_cierre_real' => 0,
            'diferencia' => 0,

            'estado' => 'abierta',

            'caja_id' => $validated['caja_id'],
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('sesiones-caja.index')
            ->with(
                'success',
                'Caja abierta correctamente.'
            );
    }

    public function cerrar(Request $request, SesionCaja $sesionCaja)
    {

        // $puedeCerrarSesionAjena = Auth::user()?->hasRole('Administrador');

        // if (
        //     $sesionCaja->user_id !== Auth::id() &&
        //     !$puedeCerrarSesionAjena
        // ) {
        //     abort(403);
        // }

        // if ($sesionCaja->estado !== 'abierta') {
        //     return back()->withErrors([
        //         'caja' => 'Esta sesión ya está cerrada.',
        //     ]);
        // }

        $validated = $request->validate([
            'monto_cierre_real' => [
                'required',
                'numeric',
                'min:0',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        //Ventas de la sesión
        $ventas = $sesionCaja->ventas()
            ->where('activo', true)
            ->with('tipoPago')
            ->get();

        //Totales por método de pago 

        $ventasEfectivo = $ventas
            ->filter(
                fn($venta) =>
                strtolower($venta->tipoPago?->nombre ?? '') === 'efectivo'
            )
            ->sum('importe_total');

        $ventasYape = $ventas
            ->filter(
                function ($venta) {
                    $nombre = strtolower(trim($venta->tipoPago?->nombre ?? ''));

                    return in_array($nombre, [
                        'yape',
                        'plin',
                        'yape/plin',
                        'yape - plin',
                        'yape / plin',
                    ]);
                }
            )
            ->sum('importe_total');

        $ventasTarjeta = $ventas
            ->filter(
                fn($venta) =>
                strtolower($venta->tipoPago?->nombre ?? '') === 'tarjeta'
            )
            ->sum('importe_total');


        //Total de ventas
        $totalVentas =
            $ventasEfectivo +
            $ventasYape +
            $ventasTarjeta;

        //Efectivo esperado
        $efectivoEsperado =
            (float) $sesionCaja->monto_apertura +
            (float) $ventasEfectivo;

        $diferencia =
            (float) $validated['monto_cierre_real'] -
            $efectivoEsperado;


        $sesionCaja->update([
            'fecha_cierre' => now(),
            'monto_cierre_teorico' => round($efectivoEsperado, 2),
            'monto_cierre_real' => round(
                $validated['monto_cierre_real'],
                2
            ),
            'diferencia' => round($diferencia, 2),
            'estado' => 'cerrada',
            'observaciones' => $validated['observaciones'] ?? null,
        ]);

        return redirect()
            ->route('sesiones-caja.index')
            ->with(
                'success',
                'Caja cerrada correctamente.'
            );
    }

    public function actual()
    {
        $sesionCaja = SesionCaja::with('caja')
            ->where('user_id', Auth::id())
            ->where('estado', 'abierta')
            ->first();

        return Inertia::render('Cajas/Actual', [
            'sesionCaja' => $sesionCaja,
        ]);
    }
}
