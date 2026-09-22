<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    public function index()
    {

        $proveedores = Proveedor::latest()->paginate(10);

        return Inertia::render(
            'Proveedores/Index',
            [
                'proveedores' => $proveedores,
            ]
        );
    }

    public function create()
    {
        return Inertia::render(
            'Proveedores/Form'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'required|string|max:255|unique:proveedors,nombre_comercial',

            'tipo_documento' => [
                'required',
                'in:ruc,dni',
            ],

            'n_documento' => 'required|string|max:15|unique:proveedors,n_documento',

            'nombre_contacto' => 'required|string|max:255',

            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',

            'activo' => 'boolean',
        ]);

        Proveedor::create($validated);

        return back()->with(
            'success',
            'Proveedor creado correctamente.'
        );
    }

    public function edit(Proveedor $proveedore)
    {
        return Inertia::render('Proveedores/Form', [
            'proveedor' => $proveedore,
        ]);
    }

    public function update(Request $request, Proveedor $proveedore)
    {
        
        $validated = $request->validate([
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'required|string|max:255',

            'tipo_documento' => [
                'required',
                'in:ruc,dni',
            ],

            'n_documento' => [
                'required',
                'string',
                'max:15',
                Rule::unique('proveedors', 'n_documento')
                    ->ignore($proveedore->id),
            ],

            'nombre_contacto' => 'required|string|max:255',

            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',

            'activo' => 'boolean',
        ]);

        $proveedore->update($validated);

        return back()->with(
            'success',
            'Proveedor actualizado correctamente.'
        );
    }

    public function show(Proveedor $proveedore)
    {
        return Inertia::render('Proveedores/Show', [
            'proveedor' => $proveedore,
        ]);
    }
}
