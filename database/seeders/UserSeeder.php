<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate([
            'email' => 'admin@helpdesk.com',
        ], [
            'name' => 'Administrador',
            'password' => Hash::make('password'),
        ]);
        $admin->syncRoles(['Administrador']);

        $empleado = User::updateOrCreate([
            'email' => 'empleado@helpdesk.com',
        ], [
            'name' => 'Empleado',
            'password' => Hash::make('password'),
        ]);
        $empleado->syncRoles(['Empleado']);

        $usuario = User::updateOrCreate([
            'email' => 'usuario@helpdesk.com',
        ], [
            'name' => 'Usuario',
            'password' => Hash::make('password'),
        ]);
        $usuario->syncRoles(['Usuario']);
    }
}
