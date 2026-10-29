<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use App\Models\Empresa;
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
                'todasLasMarcas' => Marca::orderBy('nombre')->get(['id', 'nombre', 'empresa_id']),
            ]
        );
    }

    public function asignarEmpresa(Request $request)
    {
        $validated = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'marcas' => ['present', 'array'],
            'marcas.*' => ['integer', 'distinct', 'exists:marcas,id'],
        ]);

        DB::transaction(function () use ($validated) {
            Marca::where('empresa_id', $validated['empresa_id'])
                ->whereNotIn('id', $validated['marcas'])
                ->update(['empresa_id' => null]);

            Marca::whereIn('id', $validated['marcas'])
                ->update(['empresa_id' => $validated['empresa_id']]);
        });

        return back()->with('success', 'Las marcas de la empresa fueron actualizadas correctamente.');
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
                'nullable',
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
                'nullable',
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