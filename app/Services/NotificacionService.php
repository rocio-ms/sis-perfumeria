<?php

namespace App\Services;

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
            ->get();

        foreach ($productos as $producto) {

            Notificacion::firstOrCreate(
                [
                    'tipo' => 'stock_bajo',
                    'referencia_tipo' => Producto::class,
                    'referencia_id' => $producto->id,
                    'solucionada' => false,
                ],
                [
                    'titulo' => 'Stock bajo',
                    'mensaje' => "El producto {$producto->nombre} tiene stock bajo.",
                    'leida' => false,
                ]
            );
        }

        /*
         * Si un producto ya recuperó su stock,
         * solucionamos automáticamente su alerta.
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

                if (
                    !$producto->activo ||
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
     * LOTES PRÓXIMOS A VENCER
     */
    private function sincronizarVencimientos(): void
    {
        $hoy = Carbon::today();

        $fechaLimite = $hoy->copy()->addDays(30);

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

            Notificacion::firstOrCreate(
                [
                    'tipo' => 'vencimiento',
                    'referencia_tipo' => Lote::class,
                    'referencia_id' => $lote->id,
                    'solucionada' => false,
                ],
                [
                    'titulo' => 'Lote próximo a vencer',
                    'mensaje' => "El producto {$producto->nombre} tiene un lote próximo a vencer.",
                    'leida' => false,
                ]
            );
        }

        /*
         * Si el lote ya no está dentro del rango
         * o ya no tiene stock, solucionamos la alerta.
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
                $limite = $hoy->copy()->addDays(30);

                $sigueVigente = $lote->fecha_vencimiento
                    && Carbon::parse($lote->fecha_vencimiento)->betweenIncluded(
                        $hoy,
                        $limite
                    )
                    && $lote->cantidad_actual > 0;

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

            Notificacion::firstOrCreate(
                [
                    'tipo' => 'vencido',
                    'referencia_tipo' => Lote::class,
                    'referencia_id' => $lote->id,
                    'solucionada' => false,
                ],
                [
                    'titulo' => 'Producto vencido',
                    'mensaje' => "El producto {$producto->nombre} tiene un lote vencido.",
                    'leida' => false,
                ]
            );
        }

        /*
         * Si un lote vencido ya no tiene stock,
         * solucionamos automáticamente la alerta.
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