<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderByDesc('id')->get();

        return Inertia::render(
            'Categorias/Index', 
            [
                'categorias' => $categorias,
            ]
        );
    }

    public function create()
    {
        return Inertia::render(
            'Categorias/Form'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:categorias,nombre',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'required|boolean',
        ]);

        Categoria::create($validated);

        return back()->with(
            'success',
            'Categoría creada correctamente.'
        );
    }

    public function edit(Categoria $categoria)
    {
        return Inertia::render('Categorias/Form', [
            'categoria' => $categoria,
        ]);
    }

    public function update(Request $request, Categoria $categoria)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categorias', 'nombre')->ignore($categoria->id)
            ],
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'required|boolean',
        ]);

        $categoria->update($validated);

        return back()->with(
            'success',
            'Categoría actualizada correctamente.'
        );
    }

}
