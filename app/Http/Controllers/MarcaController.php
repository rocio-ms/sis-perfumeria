<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MarcaController extends Controller
{
    public function index()
    {
        $marcas = Marca::with('empresa')
            ->orderBy('id', 'desc')
            ->paginate(10);

        $empresas = Empresa::where('activo', true)
            ->orderBy('nombre_comercial')
            ->get();

        return Inertia::render(
            'Marcas/Index',
            [
                'marcas' => $marcas,
                'empresas' => $empresas,
            ]
        );
    }

    public function create()
    {
        $empresas = Empresa::where('activo', true)
            ->orderBy('nombre_comercial')
            ->get();

        return Inertia::render(
            'Marcas/Form',
            [
                'empresas' => $empresas,
            ]
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
                'unique:marcas,nombre',
            ],

            'empresa_id' => [
                'required',
                'exists:empresas,id',
            ],

            'activo' => [
                'required',
                'boolean',
            ],
        ]);

        Marca::create($validated);

        return back()->with(
            'success',
            'Marca creada correctamente.'
        );
    }

    public function edit(Marca $marca)
    {
        $empresas = Empresa::where('activo', true)
            ->orderBy('nombre_comercial')
            ->get();

        $marca->load('empresa');

        return Inertia::render(
            'Marcas/Form',
            [
                'marca' => $marca,
                'empresas' => $empresas,
            ]
        );
    }

    public function update(Request $request, Marca $marca)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique(
                    'marcas',
                    'nombre'
                )->ignore($marca->id),
            ],

            'empresa_id' => [
                'required',
                'exists:empresas,id',
            ],

            'activo' => [
                'required',
                'boolean',
            ],
        ]);

        $marca->update($validated);

        return back()->with(
            'success',
            'Marca actualizada correctamente.'
        );
    }

}