<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CajaController extends Controller
{
    public function index(Request $request)
    {

        $search = $request->input('search');

        $query = Caja::query();

        if (!empty($search)) {
            $query->where('nombre', 'like', '%' . $search . '%');
        }

        $cajas = $query
            ->with('sesionAbierta')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Cajas/Index', [
            'cajas' => $cajas,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Cajas/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:cajas,nombre',
            'activo' => 'boolean',
            'turno' => 'required|in:mañana,tarde,noche',
        ]);

        Caja::create([
            'nombre' => $validated['nombre'],
            'activo' => $validated['activo'] ?? true,
            'turno' => $validated['turno'],
        ]);

        return redirect()
            ->route('cajas.index')
            ->with('success', 'Caja registrada correctamente');
    }

    public function show(Caja $caja)
    {
        $caja->load([
            'sesiones' => function ($query) {
                $query->with('usuario')
                    ->orderByDesc('id');
            },
        ]);

        return Inertia::render('Cajas/Show', [
            'caja' => $caja,
        ]);
    }

    public function edit(Caja $caja)
    {
        return Inertia::render('Cajas/Form', [
            'caja' => $caja,
        ]);
    }

    public function update(Request $request, Caja $caja)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:cajas,nombre,' . $caja->id,
            'activo' => 'boolean',
            'turno' => 'required|in:mañana,tarde,noche',
        ]);

        $caja->update([
            'nombre' => $validated['nombre'],
            'turno' => $validated['turno'],
            'activo' => $validated['activo'] ?? true,
        ]);

        return redirect()
            ->route('cajas.index')
            ->with('success', 'Caja actualizada correctamente.');
    }

}
