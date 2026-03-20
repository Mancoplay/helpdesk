<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos básicos
        $permissions = [
            'ver dashboard',
            'ver usuarios',
            'crear usuarios',
            'editar usuarios',
            'eliminar usuarios',
            'ver roles',
            'asignar roles',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Crear roles
        $admin = Role::create(['name' => 'Administrador']);
        $empleado = Role::create(['name' => 'Empleado']);
        $usuario = Role::create(['name' => 'Usuario']); // Clientes

        // Asignar permisos a Admin (todos)
        $admin->givePermissionTo(Permission::all());

        // Asignar permisos a Empleado
        $empleado->givePermissionTo([
            'ver dashboard',
            'ver usuarios',
        ]);

        // Asignar permisos a Usuario (solo ver dashboard)
        $usuario->givePermissionTo([
            'ver dashboard',
        ]);
    }
}