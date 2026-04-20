<?php

namespace Database\Seeders;

use App\Models\AreaTrabajo;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $laPaz = Departamento::firstOrCreate(
            ['nombre' => 'La Paz'],
            ['descripcion' => 'Departamento de Bolivia', 'activo' => true]
        );

        $sistemas = AreaTrabajo::firstOrCreate(
            ['nombre' => 'Sistemas'],
            ['descripcion' => 'Area de trabajo', 'activo' => true]
        );

        $admin = User::updateOrCreate([
            'email' => 'admin@helpdesk.com',
        ], [
            'name' => 'Admin Helpdesk',
            'nombres' => 'Admin',
            'apellidos' => 'Helpdesk',
            'password' => Hash::make('password'),
            'telefono' => '71234567',
            'activo' => true,
            'departamento_id' => $laPaz->id,
            'area_trabajo_id' => $sistemas->id,
        ]);
        $admin->syncRoles(['Administrador']);
        $admin->departamentos()->detach();

        $empleado = User::updateOrCreate([
            'email' => 'empleado@helpdesk.com',
        ], [
            'name' => 'Empleado Soporte',
            'nombres' => 'Empleado',
            'apellidos' => 'Soporte',
            'password' => Hash::make('password'),
            'telefono' => '72345678',
            'cargo' => 'Tecnico de Soporte',
            'activo' => true,
            'departamento_id' => $laPaz->id,
            'area_trabajo_id' => $sistemas->id,
        ]);
        $empleado->syncRoles(['Empleado']);
        $empleado->departamentos()->sync([$laPaz->id]);

        $usuario = User::updateOrCreate([
            'email' => 'usuario@helpdesk.com',
        ], [
            'name' => 'Usuario Demo',
            'nombres' => 'Usuario',
            'apellidos' => 'Demo',
            'password' => Hash::make('password'),
            'telefono' => '73456789',
            'activo' => true,
            'departamento_id' => $laPaz->id,
            'area_trabajo_id' => $sistemas->id,
        ]);
        $usuario->syncRoles(['Usuario']);
        $usuario->departamentos()->detach();
    }
}
