<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_index_is_available_for_all_sidebar_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $departamento = Departamento::query()->create([
            'nombre' => 'Sistemas',
            'descripcion' => 'Soporte tecnico',
            'activo' => true,
        ]);

        foreach (['Administrador', 'Empleado', 'Usuario'] as $role) {
            $user = User::factory()->create([
                'name' => $role . ' Demo',
                'nombres' => $role,
                'apellidos' => 'Demo',
                'activo' => true,
                'departamento_id' => $departamento->id,
            ]);
            $user->assignRole($role);

            if ($role === 'Empleado') {
                $user->departamentos()->sync([$departamento->id]);
            }

            $this->actingAs($user)
                ->get(route('tickets.index'))
                ->assertOk()
                ->assertViewIs('tickets.index');
        }
    }
}