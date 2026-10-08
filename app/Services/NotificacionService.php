<?php

namespace App\Services;

use App\Models\DetalleEntrada;
use App\Models\Lote;
use App\Models\Notificacion;
use App\Models\Producto;
use Carbon\Carbon;

class NotificacionService
{
    /**
     * Sincroniza todas las notificaciones automáticas.
     */
    public function sincronizar(): void
    {
        $this->sincronizarStockBajo();
        $this->sincronizarVencimientos();
        $this->sincronizarVencidos();
    }

    /**
     * STOCK BAJO
     */
    private function sincronizarStockBajo(): void
    {
        $productos = Producto::where('activo', true)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('detalle_entradas')
                    ->whereColumn(
                        'detalle_entradas.producto_id',
                        'productos.id'
                    );
            })
            ->get();

        foreach ($productos as $producto) {

            $notificacion = Notificacion::where('tipo', 'stock_bajo')
                ->where('referencia_tipo', Producto::class)
                ->where('referencia_id', $producto->id)
                ->first();

            if (!$notificacion) {

                Notificacion::create([
                    'tipo' => 'stock_bajo',
                    'titulo' => 'Stock bajo',
                    'mensaje' => "El producto {$producto->nombre} tiene stock bajo.",
                    'referencia_tipo' => Producto::class,
                    'referencia_id' => $producto->id,
                    'leida' => false,
                    'leida_at' => null,
                    'solucionada' => false,
                    'solucionada_at' => null,
                ]);

                continue;
            }

            /*
             * Si la alerta existía pero estaba solucionada
             * y el producto volvió a quedar con stock bajo,
             * se vuelve a activar.
             */
            if ($notificacion->solucionada) {
                $notificacion->update([
                    'solucionada' => false,
                    'solucionada_at' => null,
                    'leida' => false,
                    'leida_at' => null,
                ]);
            }
        }

        /*
         * Si el producto ya no tiene stock bajo,
         * se soluciona automáticamente.
         */
        Notificacion::where('tipo', 'stock_bajo')
            ->where('referencia_tipo', Producto::class)
            ->where('solucionada', false)
            ->get()
            ->each(function ($notificacion) {

                $producto = $notificacion->referencia;

                if (!$producto) {
                    return;
                }

                $tieneEntrada = DetalleEntrada::where(
                    'producto_id',
                    $producto->id
                )->exists();

                if (
                    !$producto->activo ||
                    !$tieneEntrada ||
                    $producto->stock_actual > $producto->stock_minimo
                ) {
                    $notificacion->update([
                        'solucionada' => true,
                        'solucionada_at' => now(),
                    ]);
                }
            });
    }

    /**
     * ============================================================
     * LOTES PRÓXIMOS A VENCER
     * ============================================================
     *
     * Se considera "próximo a vencer" desde hoy
     * hasta un mes después.
     */
    private function sincronizarVencimientos(): void
    {
        $hoy = Carbon::today();
        $fechaLimite = $hoy->copy()->addMonth();

        $lotes = Lote::whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->whereDate('fecha_vencimiento', '<=', $fechaLimite)
            ->where('cantidad_actual', '>', 0)
            ->with('detalleEntrada.producto')
            ->get();

        foreach ($lotes as $lote) {

            $producto = $lote->detalleEntrada?->producto;

            if (!$producto) {
                continue;
            }

            $notificacion = Notificacion::where('tipo', 'vencimiento')
                ->where('referencia_tipo', Lote::class)
                ->where('referencia_id', $lote->id)
                ->first();

            /*
             * Si nunca existió la notificación, la creamos.
             */
            if (!$notificacion) {

                Notificacion::create([
                    'tipo' => 'vencimiento',
                    'titulo' => 'Lote próximo a vencer',
                    'mensaje' => "El producto {$producto->nombre} tiene un lote próximo a vencer.",
                    'referencia_tipo' => Lote::class,
                    'referencia_id' => $lote->id,
                    'leida' => false,
                    'leida_at' => null,
                    'solucionada' => false,
                    'solucionada_at' => null,
                ]);

                continue;
            }

            /*
             * IMPORTANTE:
             *
             * Si el usuario ya solucionó manualmente esta
             * notificación, NO la volvemos a activar.
             */
        }

        /*
         * Las notificaciones pendientes que ya no cumplen
         * la condición se solucionan automáticamente.
         */
        Notificacion::where('tipo', 'vencimiento')
            ->where('referencia_tipo', Lote::class)
            ->where('solucionada', false)
            ->get()
            ->each(function ($notificacion) {

                $lote = $notificacion->referencia;

                if (!$lote) {
                    return;
                }

                $hoy = Carbon::today();
                $limite = $hoy->copy()->addMonth();

                $sigueVigente =
                    $lote->fecha_vencimiento &&
                    Carbon::parse($lote->fecha_vencimiento)
                    ->betweenIncluded($hoy, $limite) &&
                    $lote->cantidad_actual > 0;

                if (!$sigueVigente) {
                    $notificacion->update([
                        'solucionada' => true,
                        'solucionada_at' => now(),
                    ]);
                }
            });
    }

    /**
     * LOTES VENCIDOS
     */
    private function sincronizarVencidos(): void
    {
        $hoy = Carbon::today();

        $lotes = Lote::whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->where('cantidad_actual', '>', 0)
            ->with('detalleEntrada.producto')
            ->get();

        foreach ($lotes as $lote) {

            $producto = $lote->detalleEntrada?->producto;

            if (!$producto) {
                continue;
            }

            $notificacion = Notificacion::where('tipo', 'vencido')
                ->where('referencia_tipo', Lote::class)
                ->where('referencia_id', $lote->id)
                ->first();

            /*
             * Solo se crea una vez.
             *
             * Si el usuario ya solucionó la notificación,
             * no se vuelve a crear mientras siga siendo el mismo lote.
             */
            if (!$notificacion) {

                Notificacion::create([
                    'tipo' => 'vencido',
                    'titulo' => 'Producto vencido',
                    'mensaje' => "El producto {$producto->nombre} tiene un lote vencido.",
                    'referencia_tipo' => Lote::class,
                    'referencia_id' => $lote->id,
                    'leida' => false,
                    'leida_at' => null,
                    'solucionada' => false,
                    'solucionada_at' => null,
                ]);
            }
        }

        /*
         * Si el lote ya no tiene stock,
         * se soluciona automáticamente.
         */
        Notificacion::where('tipo', 'vencido')
            ->where('referencia_tipo', Lote::class)
            ->where('solucionada', false)
            ->get()
            ->each(function ($notificacion) {

                $lote = $notificacion->referencia;

                if (!$lote) {
                    return;
                }

                if ($lote->cantidad_actual <= 0) {
                    $notificacion->update([
                        'solucionada' => true,
                        'solucionada_at' => now(),
                    ]);
                }
            });
    }
}
