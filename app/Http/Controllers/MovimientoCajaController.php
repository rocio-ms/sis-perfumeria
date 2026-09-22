<?php

namespace App\Http\Controllers;

use App\Models\MovimientoCaja;
use App\Models\SesionCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MovimientoCajaController extends Controller
{


    public function store(Request $request, SesionCaja $sesionCaja)
    {
        $validated = $request->validate([
            'tipo' => 'required|in:ingreso,egreso',
            'concepto' => 'required|string|max:255',
            'monto' => 'required|numeric|min:0.01',
        ]);

        if($sesionCaja->estado !== 'abierta') {
            return response()->json([
                'success' => false,
                'message' => 'La sesión de caja está cerrada',
            ], 422);
        }

        if($sesionCaja->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes registrar movimientos en esta caja',
            ], 403);
        }

        $movimiento = MovimientoCaja::create([
            'tipo' => $validated['tipo'],
            'concepto' => $validated['concepto'],
            'monto' => $validated['monto'],
            'sesion_caja_id' => $sesionCaja->id,
            'user_id' => Auth::id(),

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento registrado correctamente',
            'data' => $movimiento,
        ]);
    }
}
