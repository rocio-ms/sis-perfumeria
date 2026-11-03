<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use App\Models\Empresa;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MarcaController extends Controller
{
    public function index()
    {
        $marcas = Marca::with('empresa')
            ->orderBy('id', 'desc')
            ->get();

        $empresas = Empresa::where('activo', true)
            ->orderBy('razon_social')
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
            ->orderBy('razon_social')
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
            ->orderBy('razon_social')
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

        DB::transaction(function () use ($marca, $validated) {
            $marca->update($validated);

            Producto::where('marca_id', $marca->id)
                ->update(['empresa_id' => $marca->empresa_id]);
        });

        return back()->with(
            'success',
            'Marca actualizada correctamente.'
        );
    }

}