<?php

namespace Database\Seeders;

use App\Models\AreaTrabajo;
use App\Models\Departamento;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HelpdeskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $laPaz = Departamento::where('nombre', 'La Paz')->firstOrFail();
        $cochabamba = Departamento::where('nombre', 'Cochabamba')->firstOrFail();
        $santaCruz = Departamento::where('nombre', 'Santa Cruz')->firstOrFail();
        $contabilidad = AreaTrabajo::where('nombre', 'Contabilidad')->firstOrFail();
        $legal = AreaTrabajo::where('nombre', 'Area Legal')->firstOrFail();
        $sistemas = AreaTrabajo::where('nombre', 'Sistemas')->firstOrFail();
        $redes = AreaTrabajo::where('nombre', 'Redes')->firstOrFail();

        $cliente1 = User::updateOrCreate(
            ['email' => 'cliente1@empresa.com'],
            [
                'name' => 'Carlos Quispe',
                'nombres' => 'Carlos',
                'apellidos' => 'Quispe',
                'telefono' => '70000001',
                'empresa' => 'Empresa Uno',
                'password' => Hash::make('password'),
                'activo' => true,
                'departamento_id' => $laPaz->id,
                'area_trabajo_id' => $contabilidad->id,
            ]
        );
        $cliente1->syncRoles(['Usuario']);
        $cliente1->departamentos()->detach();

        $cliente2 = User::updateOrCreate(
            ['email' => 'cliente2@empresa.com'],
            [
                'name' => 'Ana Mamani',
                'nombres' => 'Ana',
                'apellidos' => 'Mamani',
                'telefono' => '70000002',
                'empresa' => 'Empresa Dos',
                'password' => Hash::make('password'),
                'activo' => true,
                'departamento_id' => $santaCruz->id,
                'area_trabajo_id' => $legal->id,
            ]
        );
        $cliente2->syncRoles(['Usuario']);
        $cliente2->departamentos()->detach();

        $empleado1 = User::updateOrCreate(
            ['email' => 'tecnico1@helpdesk.com'],
            [
                'name' => 'Juan Perez',
                'nombres' => 'Juan',
                'apellidos' => 'Perez',
                'telefono' => '71111111',
                'cargo' => 'Tecnico de Soporte',
                'password' => Hash::make('password'),
                'activo' => true,
                'departamento_id' => $laPaz->id,
                'area_trabajo_id' => $sistemas->id,
            ]
        );
        $empleado1->syncRoles(['Empleado']);
        $empleado1->departamentos()->sync([$laPaz->id]);

        $empleado2 = User::updateOrCreate(
            ['email' => 'tecnico2@helpdesk.com'],
            [
                'name' => 'Lucia Rojas',
                'nombres' => 'Lucia',
                'apellidos' => 'Rojas',
                'telefono' => '72222222',
                'cargo' => 'Especialista de Redes',
                'password' => Hash::make('password'),
                'activo' => true,
                'departamento_id' => $cochabamba->id,
                'area_trabajo_id' => $redes->id,
            ]
        );
        $empleado2->syncRoles(['Empleado']);
        $empleado2->departamentos()->sync([$cochabamba->id]);

        Ticket::updateOrCreate(
            ['codigo' => 'TCK-0001'],
            [
                'cliente_id' => $cliente1->id,
                'empleado_id' => $empleado1->id,
                'departamento_id' => $laPaz->id,
                'asunto' => 'No puedo acceder al sistema',
                'descripcion' => 'El cliente reporta error de autenticacion.',
                'estado' => 'pendiente',
            ]
        );

        Ticket::updateOrCreate(
            ['codigo' => 'TCK-0002'],
            [
                'cliente_id' => $cliente2->id,
                'empleado_id' => $empleado2->id,
                'departamento_id' => $cochabamba->id,
                'asunto' => 'Intermitencia en la red',
                'descripcion' => 'Se reportan cortes de red en horas pico.',
                'estado' => 'en_proceso',
            ]
        );
    }
}
