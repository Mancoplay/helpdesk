<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Ticket;
use Illuminate\Database\Seeder;

class HelpdeskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $departamentoSoporte = Departamento::firstOrCreate(
            ['nombre' => 'Soporte Tecnico'],
            ['descripcion' => 'Mesa de ayuda principal', 'activo' => true]
        );

        $departamentoRedes = Departamento::firstOrCreate(
            ['nombre' => 'Redes'],
            ['descripcion' => 'Infraestructura y conectividad', 'activo' => true]
        );

        Departamento::firstOrCreate(
            ['nombre' => 'Desarrollo'],
            ['descripcion' => 'Aplicaciones internas', 'activo' => true]
        );

        $cliente1 = Cliente::firstOrCreate(
            ['email' => 'cliente1@empresa.com'],
            [
                'nombres' => 'Carlos',
                'apellidos' => 'Quispe',
                'telefono' => '70000001',
                'empresa' => 'Empresa Uno',
                'activo' => true,
            ]
        );

        $cliente2 = Cliente::firstOrCreate(
            ['email' => 'cliente2@empresa.com'],
            [
                'nombres' => 'Ana',
                'apellidos' => 'Mamani',
                'telefono' => '70000002',
                'empresa' => 'Empresa Dos',
                'activo' => true,
            ]
        );

        $empleado1 = Empleado::firstOrCreate(
            ['email' => 'tecnico1@helpdesk.com'],
            [
                'departamento_id' => $departamentoSoporte->id,
                'nombres' => 'Juan',
                'apellidos' => 'Perez',
                'telefono' => '71111111',
                'cargo' => 'Tecnico de Soporte',
                'activo' => true,
            ]
        );

        $empleado2 = Empleado::firstOrCreate(
            ['email' => 'tecnico2@helpdesk.com'],
            [
                'departamento_id' => $departamentoRedes->id,
                'nombres' => 'Lucia',
                'apellidos' => 'Rojas',
                'telefono' => '72222222',
                'cargo' => 'Especialista de Redes',
                'activo' => true,
            ]
        );

        Ticket::firstOrCreate(
            ['codigo' => 'TCK-0001'],
            [
                'cliente_id' => $cliente1->id,
                'empleado_id' => $empleado1->id,
                'departamento_id' => $departamentoSoporte->id,
                'asunto' => 'No puedo acceder al sistema',
                'descripcion' => 'El cliente reporta error de autenticacion.',
                'estado' => 'pendiente',
                'prioridad' => 'alta',
            ]
        );

        Ticket::firstOrCreate(
            ['codigo' => 'TCK-0002'],
            [
                'cliente_id' => $cliente2->id,
                'empleado_id' => $empleado2->id,
                'departamento_id' => $departamentoRedes->id,
                'asunto' => 'Intermitencia en la red',
                'descripcion' => 'Se reportan cortes de red en horas pico.',
                'estado' => 'en_proceso',
                'prioridad' => 'media',
            ]
        );
    }
}

