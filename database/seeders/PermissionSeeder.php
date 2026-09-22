<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $permisos = [
            'dashboard.index',

            'ventas.index',
            'ventas.ver',
            'ventas.crear',
            'ventas.editar',

            'clientes.index',
            'clientes.ver',
            'clientes.crear',
            'clientes.editar',

            'cajas.index',
            'cajas.crear',
            'cajas.editar',

            'sesiones-caja.index',
            'sesiones-caja.ver',
            'sesiones-caja.abrir',
            'sesiones-caja.cerrar',

            'productos.index',
            'productos.ver',
            'productos.crear',
            'productos.editar',

            'categorias.index',
            'categorias.crear',
            'categorias.editar',

            'marcas.index',
            'marcas.crear',
            'marcas.editar',

            'entradas.index',
            'entradas.ver',
            'entradas.crear',
            'entradas.editar',

            'salidas.index',
            'salidas.ver',
            'salidas.crear',
            'salidas.editar',

            'kardex.index',

            'lotes.index',

            'proveedores.index',
            'proveedores.ver',
            'proveedores.crear',
            'proveedores.editar',

            'notificaciones.index',

            'configuracion.index',

            'usuarios.index',
            'usuarios.crear',
            'usuarios.editar',
        ];

        //Crear permisos

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }

        //Roles

        $administrador = Role::firstOrCreate([
            'name' => 'Administrador',
            'guard_name' => 'web',
        ]);

        $vendedor = Role::firstOrCreate([
            'name' => 'Vendedor',
            'guard_name' => 'web',
        ]);


        //Asignando permisos al admin
        $administrador->syncPermissions(
            Permission::where('guard_name', 'web')->get()
        );


        //Asignando permisos al vendedor
        $vendedor->syncPermissions([
            'ventas.index',
            'ventas.ver',
            'ventas.crear',

            'clientes.index',
            'clientes.ver',

            'sesiones-caja.index',
            'sesiones-caja.ver',
            'sesiones-caja.abrir',
            'sesiones-caja.cerrar',

            'productos.index',
            'productos.ver',

            'notificaciones.index',

        ]);

        $adminUser = User::where('username', 'admin')->first();
        if ($adminUser) {
            $adminUser->syncRoles(['Administrador']);
        }

        //Limpiar caché
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
