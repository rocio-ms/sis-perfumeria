<?php

namespace App\Http\Controllers;

use App\Models\PrecioProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrecioProductoController extends Controller
{

    public function store(Request $request)
    {
        $datos = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'precio_venta' => 'required|numeric|min:0',
            'tiene_descuento' => 'boolean',
            'descuento' => 'nullable|numeric|min:0',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'activo' => 'boolean',
        ]);

        $tieneDescuento = $datos['tiene_descuento'] ?? false;
        $descuento = $datos['descuento'] ?? 0;

        //Tiene descuento

        if ($tieneDescuento && $descuento > $datos['precio_venta']) {
            return back()->withErrors([
                'descuento' =>
                'El descuento no puede ser mayor al precio de venta.',
            ]);
        }


        DB::transaction(function () use ($datos, $tieneDescuento, $descuento) {

            // Desactivar el precio actualmente activo
            PrecioProducto::where('producto_id', $datos['producto_id'])
                ->where('activo', true)
                ->update([
                    'activo' => false,
                    'fecha_fin' => now()->toDateString(),
                ]);

            // Crear el nuevo precio
            PrecioProducto::create([
                'producto_id' => $datos['producto_id'],
                'precio_venta' => $datos['precio_venta'],
                'tiene_descuento' => $tieneDescuento,
                'descuento' => $tieneDescuento ? $descuento : 0,
                'fecha_inicio' =>
                !empty($datos['fecha_inicio'])
                    ? date('Y-m-d', strtotime($datos['fecha_inicio']))
                    : now()->toDateString(),
                'fecha_fin' => null,
                'activo' => true,
            ]);
        });

        return back()->with(
            'success',
            'Precio del producto actualizado correctamente.'
        );
    }

    public function update(Request $request, PrecioProducto $precioProducto)
    {
        $datos = $request->validate([
            'precio_venta' => 'required|numeric|min:0',
            'tiene_descuento' => 'boolean',
            'descuento' => 'nullable|numeric|min:0',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'activo' => 'boolean',
        ]);
        $tieneDescuento = $datos['tiene_descuento'] ?? false;
        $descuento = $datos['descuento'] ?? 0;

        if ($tieneDescuento && $descuento > $datos['precio_venta']) {
            return back()->withErrors([
                'descuento' =>
                'El descuento no puede ser mayor al precio de venta.',
            ]);
        }

        $fechaInicio = null;

        if (!empty($datos['fecha_inicio'])) {
            $fechaInicio = date(
                'Y-m-d',
                strtotime($datos['fecha_inicio'])
            );
        }

        $fechaFin = null;

        if (!empty($datos['fecha_fin'])) {
            $fechaFin = date(
                'Y-m-d',
                strtotime($datos['fecha_fin'])
            );
        }

        $precioProducto->update([
            'precio_venta' => $datos['precio_venta'],
            'tiene_descuento' => $tieneDescuento,
            'descuento' => $tieneDescuento ? $descuento : 0,
            'fecha_inicio' => $fechaInicio ??
                $precioProducto->fecha_inicio,
            'fecha_fin' => $fechaFin,
            'activo' => $datos['activo'] ?? true,
        ]);

        return back()->with(
            'success',
            'Precio actualizado correctamente.'
        );
    }

    public function destroy(PrecioProducto $precioProducto)
    {
        $precioProducto->delete();

        return back()->with(
            'success',
            'Precio eliminado correctamente.'
        );
    }
}
