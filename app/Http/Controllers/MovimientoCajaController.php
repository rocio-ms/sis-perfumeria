<?php

namespace App\Http\Controllers;

use App\Models\SesionCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovimientoCajaController extends Controller
{


    public function store(Request $request)
    {
        $validated = $request->validate([
            'sesion_caja_id' => 'required|integer|exists:sesion_cajas,id',
            'tipo' => 'required|in:ingreso,egreso',
            'concepto' => 'required|string|max:255',
            'monto' => 'required|numeric|min:0.01',
        ]);

        $movimiento = DB::transaction(function () use ($validated) {
            $sesion = SesionCaja::whereKey($validated['sesion_caja_id'])->lockForUpdate()->firstOrFail();
            abort_unless($sesion->user_id === Auth::id(), 403);

            if ($sesion->estado !== 'abierta') {
                throw ValidationException::withMessages(['sesion_caja_id' => 'La sesión de caja está cerrada.']);
            }

            return $sesion->movimientos()->create([
                'tipo' => $validated['tipo'],
                'concepto' => $validated['concepto'],
                'monto' => $validated['monto'],
                'user_id' => Auth::id(),
            ]);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento registrado correctamente',
            'data' => $movimiento,
        ]);
    }
}
