<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Administrador
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@helpdesk.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('Administrador');

        // Empleado
        $empleado = User::create([
            'name' => 'Juan Pérez',
            'email' => 'empleado@helpdesk.com',
            'password' => Hash::make('password'),
        ]);
        $empleado->assignRole('Empleado');

        // Usuario normal (cliente)
        $usuario = User::create([
            'name' => 'Carlos Rodríguez',
            'email' => 'usuario@helpdesk.com',
            'password' => Hash::make('password'),
        ]);
        $usuario->assignRole('Usuario');
    }
}