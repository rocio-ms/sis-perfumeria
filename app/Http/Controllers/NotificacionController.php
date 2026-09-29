<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificacionController extends Controller
{
    /**
     * Mostrar listado de notificaciones.
     */
    public function index(Request $request)
    {
        $query = Notificacion::query()
            ->orderByDesc('created_at');

        /*
        |--------------------------------------------------------------------------
        | Búsqueda
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('mensaje', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por tipo
        |--------------------------------------------------------------------------
        */

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por estado de lectura
        |--------------------------------------------------------------------------
        */

        if ($request->filled('estado')) {

            switch ($request->estado) {
                case 'no_leidas':
                    $query->where('leida', false);
                    break;
                case 'leidas':
                    $query->where('leida', true);
                    break;
                case 'pendientes':
                    $query->where('solucionada', false);
                    break;
                case 'solucionadas':
                    $query->where('solucionada', true);
                    break;
            }
        }

        $notificaciones = $query
            ->paginate(10)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Contador de no leídas
        |--------------------------------------------------------------------------
        */

        $noLeidas = Notificacion::where('leida', false)->count();
        $pendientes = Notificacion::where('solucionada', false)->count();
        $solucionadas = Notificacion::where('solucionada', true)->count();

        /*
        |--------------------------------------------------------------------------
        | Tipos disponibles
        |--------------------------------------------------------------------------
        */

        $tipos = Notificacion::query()
            ->select('tipo')
            ->distinct()
            ->orderBy('tipo')
            ->pluck('tipo');

        return Inertia::render('Notificaciones/Index', [
            'notificaciones' => $notificaciones,
            'noLeidas' => $noLeidas,
            'solucionadas' => $solucionadas,
            'tipos' => $tipos,
            'filters' => [
                'search' => $request->search,
                'tipo' => $request->tipo,
                'estado' => $request->estado,
            ],
        ]);
    }

    /**
     * Marcar una notificación como leída.
     */
    public function marcarLeida(Notificacion $notificacion)
    {
        if (!$notificacion->leida) {
            $notificacion->update([
                'leida' => true,
                'leida_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Marcar todas las notificaciones como leídas.
     */
    public function marcarTodasLeidas()
    {
        Notificacion::where('leida', false)
            ->update([
                'leida' => true,
                'leida_at' => now(),
            ]);

        return back();
    }

    /**
     * Marcar una notificación como solucionada.
     */
    public function solucionar(Notificacion $notificacion)
    {
        $notificacion->update([
            'solucionada' => true,
            'solucionada_at' => now(),
        ]);

        return back();
    }

    /**
     * Eliminar una notificación.
     */
    public function destroy(Notificacion $notificacion)
    {
        $notificacion->delete();

        return back();
    }
}
