<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'ver dashboard',
            'ver tickets',
            'crear tickets',
            'atender tickets',
            'gestionar clientes',
            'gestionar empleados',
            'gestionar departamentos',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'Administrador']);
        $empleado = Role::firstOrCreate(['name' => 'Empleado']);
        $cliente = Role::firstOrCreate(['name' => 'Cliente']);
        $usuario = Role::firstOrCreate(['name' => 'Usuario']);

        $admin->syncPermissions(Permission::all());

        $cliente->syncPermissions([
            'ver dashboard',
            'ver tickets',
            'crear tickets',
        ]);

        $usuario->syncPermissions([
            'ver dashboard',
            'ver tickets',
            'crear tickets',
        ]);

        $empleado->syncPermissions([
            'ver dashboard',
            'ver tickets',
            'atender tickets',
        ]);
    }
}

