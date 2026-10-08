<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tienda;
use Inertia\Inertia;

class TiendaController extends Controller
{
    public function index()
    {
        $tienda = Tienda::first();

        return Inertia::render('Tiendas/Index', [
            'tienda' => $tienda,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ruc' => [
                'required',
                'digits:11',
                'unique:tiendas,ruc',
            ],

            'razon_social' => [
                'required',
                'string',
                'max:255',
            ],

            'nombre_comercial' => [
                'required',
                'string',
                'max:150',
            ],

            'direccion' => [
                'required',
                'string',
                'max:255',
            ],

            'ubigeo' => [
                'nullable',
                'string',
                'size:6',
            ],

            'departamento' => [
                'nullable',
                'string',
                'max:100',
            ],

            'provincia' => [
                'nullable',
                'string',
                'max:100',
            ],

            'distrito' => [
                'nullable',
                'string',
                'max:100',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('logo')) {
            $ruta = $request->file('logo')->store(
                'tiendas',
                'public'
            );

            $validated['logo_url'] = $ruta;
        }

        unset($validated['logo']);

        Tienda::create($validated);

        return redirect()
            ->route('tiendas.index')
            ->with('success', 'La tienda fue registrada correctamente.');
    }

    public function update(Request $request, Tienda $tienda)
    {
        $validated = $request->validate([
            'ruc' => [
                'required',
                'digits:11',
                'unique:tiendas,ruc,' . $tienda->id,
            ],

            'razon_social' => [
                'required',
                'string',
                'max:255',
            ],

            'nombre_comercial' => [
                'required',
                'string',
                'max:150',
            ],

            'direccion' => [
                'required',
                'string',
                'max:255',
            ],

            'ubigeo' => [
                'nullable',
                'string',
                'size:6',
            ],

            'departamento' => [
                'nullable',
                'string',
                'max:100',
            ],

            'provincia' => [
                'nullable',
                'string',
                'max:100',
            ],

            'distrito' => [
                'nullable',
                'string',
                'max:100',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'serie_boleta' => [
                'nullable',
                'string',
                'max:4',
            ],

            'serie_factura' => [
                'nullable',
                'string',
                'max:4',
            ],

            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('logo')) {
            $ruta = $request->file('logo')->store(
                'tiendas',
                'public'
            );

            $validated['logo_url'] = $ruta;
        }

        unset($validated['logo']);

        $tienda->logo_url = $validated['logo_url'];
        $tienda->save();

        return redirect()
            ->route('tiendas.index')
            ->with('success', 'La tienda fue actualizada correctamente.');
    }
}
