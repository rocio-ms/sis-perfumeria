<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    /**
     * Mostrar listado de usuarios
     */
    public function index()
    {
        $usuarios = User::with('roles')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
        ]);
    }

    /**
     * Registrar usuario
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'dni' => [
                'required',
                'digits:8',
                'unique:users,dni',
            ],

            'username' => [
                'required',
                'string',
                'max:255',
                'unique:users,username',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'activo' => [
                'required',
                'boolean',
            ],

            'rol' => [
                'required',
                'exists:roles,id',
            ],
        ]);

        $rolId = $validated['rol'];

        unset($validated['rol']);

        $usuario = User::create($validated);

        $rol = Role::findOrFail($rolId);

        $usuario->assignRole($rol);

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuario registrado correctamente.');
    }

    /**
     * Mostrar usuario
     */
    public function show(User $usuario)
    {
        $usuario->load('roles');

        return Inertia::render('Usuarios/Show', [
            'usuario' => $usuario,
        ]);
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'dni' => [
                'required',
                'digits:8',
                'unique:users,dni,' . $usuario->id,
            ],

            'username' => [
                'required',
                'string',
                'max:255',
                'unique:users,username,' . $usuario->id,
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email,' . $usuario->id,
            ],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],

            'activo' => [
                'required',
                'boolean',
            ],

            'rol' => [
                'required',
                'exists:roles,id',
            ],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $rolId = $validated['rol'];

        unset($validated['rol']);

        $usuario->update($validated);

        $rol = Role::findOrFail($rolId);

        $usuario->syncRoles([$rol]);

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

}
