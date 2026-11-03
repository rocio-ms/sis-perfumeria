<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermisosController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('RolesPermisos/Index', [
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->with('permissions:id,name')
                ->orderByRaw("CASE WHEN name = 'Administrador' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name']),
            'permisos' => Permission::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name']),
            'nuevoRolId' => $request->session()->get('nuevoRolId'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'permissions' => [
                'sometimes',
                'array',
            ],
            'permissions.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ]);

        $role = DB::transaction(function () use ($validated) {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(
                Permission::whereIn('id', $validated['permissions'] ?? [])->get()
            );

            return $role;
        });

        return redirect()
            ->route('roles-permisos.index')
            ->with('nuevoRolId', $role->id)
            ->with('success', 'El rol fue creado correctamente.');
    }

    public function update(Request $request, Role $role)
    {
        abort_if(
            $role->guard_name !== 'web' || $role->name === 'Administrador',
            403
        );

        $validated = $request->validate([
            'permissions' => [
                'present',
                'array',
            ],
            'permissions.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ]);

        $role->syncPermissions(
            Permission::whereIn('id', $validated['permissions'])->get()
        );

        return back()->with('success', 'Los permisos del rol fueron actualizados.');
    }
}
