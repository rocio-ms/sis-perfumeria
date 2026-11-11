<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\SesionCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            ->withSum(['movimientos as ingresos_varios' => fn ($query) => $query->where('tipo', 'ingreso')], 'monto')
            ->withSum(['movimientos as egresos_varios' => fn ($query) => $query->where('tipo', 'egreso')], 'monto')
            ->when($search, function ($query, $search) {
                $query->whereHas('caja', function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('fecha_apertura')
            ->get();

        $sesiones->transform(function ($sesion) {

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

            $sesion->ventas_total = $ventasActivas->sum('importe_total');
            $sesion->ventas_transferencia = $ventasActivas
                ->filter(fn ($venta) => strtolower(trim($venta->tipoPago?->nombre ?? '')) === 'transferencia')
                ->sum('importe_total');

            $sesion->efectivo_esperado =
                (float) $sesion->monto_apertura +
                (float) $sesion->ventas_efectivo +
                (float) $sesion->ingresos_varios -
                (float) $sesion->egresos_varios;

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

        DB::transaction(function () use ($request, $validated) {
            $request->user()->newQuery()->whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            $caja = Caja::whereKey($validated['caja_id'])->lockForUpdate()->firstOrFail();

            if (!$caja->activo || $caja->sesiones()->where('estado', 'abierta')->exists()) {
                throw ValidationException::withMessages(['caja_id' => 'La caja seleccionada ya no está disponible.']);
            }

            if (SesionCaja::where('user_id', Auth::id())->where('estado', 'abierta')->exists()) {
                throw ValidationException::withMessages(['caja_id' => 'Cierra tu sesión de caja actual antes de abrir otra.']);
            }

            SesionCaja::create([
                'fecha_apertura' => now(),
                'monto_apertura' => $validated['monto_apertura'],
                'monto_cierre_teorico' => 0,
                'monto_cierre_real' => 0,
                'diferencia' => 0,
                'estado' => 'abierta',
                'caja_id' => $caja->id,
                'user_id' => Auth::id(),
            ]);
        }, 3);

        return redirect()
            ->route('sesiones-caja.index')
            ->with(
                'success',
                'Caja abierta correctamente.'
            );
    }

    public function cerrar(Request $request, SesionCaja $sesionCaja)
    {
        $validated = $request->validate([
            'monto_cierre_real' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:255',
        ]);

        abort_unless($sesionCaja->user_id === Auth::id() || $request->user()->hasRole('Administrador'), 403);

        DB::transaction(function () use ($sesionCaja, $validated) {
            $sesion = SesionCaja::whereKey($sesionCaja->id)->lockForUpdate()->firstOrFail();

            if ($sesion->estado !== 'abierta') {
                throw ValidationException::withMessages(['caja' => 'Esta sesión ya está cerrada.']);
            }

            $ventasEfectivo = $sesion->ventas()
                ->where('activo', true)
                ->whereHas('tipoPago', fn ($query) => $query->whereRaw("LOWER(TRIM(nombre)) = 'efectivo'"))
                ->sum('importe_total');

            $ingresos = $sesion->movimientos()->where('tipo', 'ingreso')->sum('monto');
            $egresos = $sesion->movimientos()->where('tipo', 'egreso')->sum('monto');
            $efectivoEsperado = round($sesion->monto_apertura + $ventasEfectivo + $ingresos - $egresos, 2);

            $sesion->update([
                'fecha_cierre' => now(),
                'monto_cierre_teorico' => $efectivoEsperado,
                'monto_cierre_real' => round($validated['monto_cierre_real'], 2),
                'diferencia' => round($validated['monto_cierre_real'] - $efectivoEsperado, 2),
                'estado' => 'cerrada',
                'observaciones' => $validated['observaciones'] ?? null,
            ]);
        }, 3);

        return redirect()->route('sesiones-caja.index')->with('success', 'Caja cerrada correctamente.');
    }

    public function actual()
    {
        $sesionCaja = SesionCaja::with('caja')
            ->where('user_id', Auth::id())
            ->where('estado', 'abierta')
            ->first();

        return response()->json([
            'sesionCaja' => $sesionCaja,
        ]);
    }

    public function movimientos(Request $request, SesionCaja $sesionCaja)
    {
        abort_unless($sesionCaja->user_id === Auth::id() || $request->user()->hasRole('Administrador'), 403);

        return response()->json(['movimientos' => $sesionCaja->movimientos()->latest('id')->get()]);
    }
}
