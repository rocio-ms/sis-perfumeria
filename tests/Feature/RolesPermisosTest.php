<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermisosTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConRol(string $nombreRol): User
    {
        $role = Role::create([
            'name' => $nombreRol,
            'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => $nombreRol,
            'dni' => (string) random_int(10000000, 99999999),
            'username' => Str::uuid()->toString(),
            'email' => Str::uuid()->toString().'@example.test',
            'password' => Hash::make('password'),
            'activo' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }

    public function test_administrator_can_create_a_role_and_manage_its_permissions(): void
    {
        $admin = $this->usuarioConRol('Administrador');

        $viewPermission = Permission::create([
            'name' => 'productos.index',
            'guard_name' => 'web',
        ]);
        $editPermission = Permission::create([
            'name' => 'productos.editar',
            'guard_name' => 'web',
        ]);

        $roleResponse = $this->actingAs($admin)->post(
            route('roles-permisos.store'),
            [
                'name' => 'Encargado de productos',
                'permissions' => [$viewPermission->id],
            ]
        );

        $roleResponse->assertRedirect(route('roles-permisos.index'));
        $role = Role::where('name', 'Encargado de productos')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo($viewPermission));

        $this->put(route('roles-permisos.update', $role), [
            'permissions' => [$editPermission->id],
        ])->assertRedirect();

        $role->refresh();
        $this->assertFalse($role->hasPermissionTo($viewPermission));
        $this->assertTrue($role->hasPermissionTo($editPermission));
    }

    public function test_non_administrator_cannot_manage_roles(): void
    {
        $seller = $this->usuarioConRol('Vendedor');

        $this->actingAs($seller)
            ->get(route('roles-permisos.index'))
            ->assertForbidden();
    }

    public function test_administrator_role_permissions_cannot_be_changed(): void
    {
        $admin = $this->usuarioConRol('Administrador');
        $adminRole = Role::where('name', 'Administrador')->firstOrFail();
        $permission = Permission::create([
            'name' => 'productos.index',
            'guard_name' => 'web',
        ]);

        $this->actingAs($admin)
            ->put(route('roles-permisos.update', $adminRole), [
                'permissions' => [$permission->id],
            ])
            ->assertForbidden();
    }
}
