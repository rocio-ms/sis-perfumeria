<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubcategoriaController extends Controller
{
    public function index()
    {
        $subcategorias = Subcategoria::with('categoria')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return Inertia::render('Subcategorias/Index', [
            'subcategorias' => $subcategorias,
            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Subcategorias/Form', [
            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subcategorias')->where(fn ($query) => $query->where('categoria_id', $request->categoria_id)),
            ],
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'required|boolean',
        ]);

        Subcategoria::create($validated);

        return back()->with(
            'success',
            'Subcategoría creada correctamente.'
        );
    }

    public function edit(Subcategoria $subcategoria)
    {
        $subcategoria->load('categoria');

        return Inertia::render('Subcategorias/Form', [
            'subcategoria' => $subcategoria,
            'categorias' => Categoria::where('activo', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function update(Request $request, Subcategoria $subcategoria)
    {
        $validated = $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subcategorias', 'nombre')
                    ->where('categoria_id', $request->categoria_id)
                    ->ignore($subcategoria->id),
            ],
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'required|boolean',
        ]);

        $subcategoria->update($validated);

        return back()->with(
            'success',
            'Subcategoría actualizada correctamente.'
        );
    }
}
