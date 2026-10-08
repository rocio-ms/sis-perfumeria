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
            ->with([
                'referencia',
            ])
            ->orderByDesc('created_at');

        /*
    |--------------------------------------------------------------------------
    | BUSCADOR
    |--------------------------------------------------------------------------
    |
    | Busca por:
    | - título de la notificación
    | - mensaje
    | - nombre del producto
    | - código del producto
    | - código de barras
    |
    */
        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                // Buscar en título
                $q->where('titulo', 'like', "%{$search}%")

                    // Buscar en mensaje
                    ->orWhere('mensaje', 'like', "%{$search}%")

                    // Buscar producto relacionado
                    ->orWhereHasMorph(
                        'referencia',
                        [\App\Models\Producto::class],
                        function ($productoQuery) use ($search) {
                            $productoQuery
                                ->where('nombre', 'like', "%{$search}%")
                                ->orWhere('codigo', 'like', "%{$search}%")
                                ->orWhere(
                                    'codigo_barras',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    )

                    // Buscar producto cuando la referencia es un Lote
                    ->orWhereHasMorph(
                        'referencia',
                        [\App\Models\Lote::class],
                        function ($loteQuery) use ($search) {

                            $loteQuery->whereHas(
                                'detalleEntrada.producto',
                                function ($productoQuery) use ($search) {

                                    $productoQuery->where(function ($q) use ($search) {

                                        $q->where(
                                            'nombre',
                                            'like',
                                            "%{$search}%"
                                        )
                                            ->orWhere(
                                                'codigo',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'codigo_barras',
                                                'like',
                                                "%{$search}%"
                                            );
                                    });
                                }
                            );
                        }
                    );
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
    | FILTRO POR ESTADO
    |--------------------------------------------------------------------------
    */

        if ($request->filled('estado')) {

            switch ($request->estado) {

                case 'no_leidas':

                    $query->where('leida', false)
                        ->where('solucionada', false);

                    break;

                case 'leidas':

                    $query->where('leida', true)
                        ->where('solucionada', false);

                    break;

                case 'pendientes':

                    $query->where('solucionada', false);

                    break;

                case 'solucionadas':

                    $query->where('solucionada', true);

                    break;
            }
        }

        // Paginación

        $notificaciones = $query
            ->paginate(10)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Contador de no leídas
        |--------------------------------------------------------------------------
        */

        $noLeidas = Notificacion::where('leida', false)
            ->where('solucionada', false)
            ->count();
        $pendientes = Notificacion::where('solucionada', false)->count();
        $solucionadas = Notificacion::where('solucionada', true)->count();

        /*
        |--------------------------------------------------------------------------
        | Tipos disponibles
        |--------------------------------------------------------------------------
        */

        $tipos = [
            'stock_bajo',
            'vencimiento',
            'vencido',
        ];

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
            ->where('solucionada', false)
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
            'leida' => true,
            'leida_at' => now(),
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
