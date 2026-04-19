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
        $departamentosBolivia = [
            'La Paz',
            'Cochabamba',
            'Santa Cruz',
            'Oruro',
            'Potosi',
            'Chuquisaca',
            'Tarija',
            'Beni',
            'Pando',
        ];

        foreach ($departamentosBolivia as $departamento) {
            Departamento::firstOrCreate(
                ['nombre' => $departamento],
                ['descripcion' => 'Departamento de Bolivia', 'activo' => true]
            );
        }

        $areasTrabajo = [
            'Area Legal',
            'Contabilidad',
            'Reclamos',
            'RRHH',
            'Soporte Tecnico',
            'Sistemas',
            'Redes',
            'Atencion al Cliente',
        ];

        foreach ($areasTrabajo as $area) {
            AreaTrabajo::firstOrCreate(
                ['nombre' => $area],
                ['descripcion' => 'Area de trabajo', 'activo' => true]
            );
        }

        $laPaz = Departamento::where('nombre', 'La Paz')->firstOrFail();
        $cochabamba = Departamento::where('nombre', 'Cochabamba')->firstOrFail();
        $santaCruz = Departamento::where('nombre', 'Santa Cruz')->firstOrFail();
        $soporte = AreaTrabajo::where('nombre', 'Soporte Tecnico')->firstOrFail();
        $rrhh = AreaTrabajo::where('nombre', 'RRHH')->firstOrFail();
        $reclamos = AreaTrabajo::where('nombre', 'Reclamos')->firstOrFail();

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
            'area_trabajo_id' => $rrhh->id,
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
            'departamento_id' => $cochabamba->id,
            'area_trabajo_id' => $soporte->id,
        ]);
        $empleado->syncRoles(['Empleado']);
        $empleado->departamentos()->sync([$cochabamba->id]);

        $usuario = User::updateOrCreate([
            'email' => 'usuario@helpdesk.com',
        ], [
            'name' => 'Usuario Demo',
            'nombres' => 'Usuario',
            'apellidos' => 'Demo',
            'password' => Hash::make('password'),
            'telefono' => '73456789',
            'activo' => true,
            'departamento_id' => $santaCruz->id,
            'area_trabajo_id' => $reclamos->id,
        ]);
        $usuario->syncRoles(['Usuario']);
        $usuario->departamentos()->detach();
    }
}
