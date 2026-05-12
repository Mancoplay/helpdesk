<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAttendedDatabaseNotification;
use App\Notifications\TicketFinalizedDatabaseNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketAttendTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_attend_pending_ticket_without_running_notification_inline(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
        Notification::fake();

        $departamento = Departamento::query()->create([
            'nombre' => 'Sistemas',
            'descripcion' => 'Soporte tecnico',
            'activo' => true,
        ]);

        $employee = User::factory()->create([
            'name' => 'Empleado Soporte',
            'nombres' => 'Empleado',
            'apellidos' => 'Soporte',
            'activo' => true,
            'departamento_id' => $departamento->id,
        ]);
        $employee->assignRole('Empleado');
        $employee->departamentos()->sync([$departamento->id]);

        $client = User::factory()->create([
            'name' => 'Usuario Demo',
            'nombres' => 'Usuario',
            'apellidos' => 'Demo',
            'activo' => true,
            'departamento_id' => $departamento->id,
        ]);
        $client->assignRole('Usuario');

        $ticket = Ticket::query()->create([
            'codigo' => 'TCK-0001',
            'cliente_id' => $client->id,
            'departamento_id' => $departamento->id,
            'asunto' => 'No enciende el equipo',
            'descripcion' => 'El equipo no inicia.',
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($employee)->patch(route('tickets.attend', $ticket));

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'empleado_id' => $employee->id,
            'estado' => 'en_proceso',
        ]);

        $this->assertDatabaseHas('ticket_eventos', [
            'ticket_id' => $ticket->id,
            'user_id' => $employee->id,
            'event_type' => 'mensaje',
            'tipo' => 'atencion',
        ]);

        Notification::assertSentTo(Cliente::query()->findOrFail($client->id), TicketAttendedDatabaseNotification::class);
    }

    public function test_employee_finalizing_ticket_notifies_ticket_creator(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
        Notification::fake();

        $departamento = Departamento::query()->create([
            'nombre' => 'Sistemas',
            'descripcion' => 'Soporte tecnico',
            'activo' => true,
        ]);

        $employee = User::factory()->create([
            'name' => 'Empleado Soporte',
            'nombres' => 'Empleado',
            'apellidos' => 'Soporte',
            'activo' => true,
            'departamento_id' => $departamento->id,
        ]);
        $employee->assignRole('Empleado');
        $employee->departamentos()->sync([$departamento->id]);

        $client = User::factory()->create([
            'name' => 'Usuario Demo',
            'nombres' => 'Usuario',
            'apellidos' => 'Demo',
            'activo' => true,
            'departamento_id' => $departamento->id,
        ]);
        $client->assignRole('Usuario');

        $ticket = Ticket::query()->create([
            'codigo' => 'TCK-0002',
            'cliente_id' => $client->id,
            'empleado_id' => $employee->id,
            'departamento_id' => $departamento->id,
            'asunto' => 'No imprime',
            'descripcion' => 'La impresora no responde.',
            'estado' => 'en_proceso',
        ]);

        $response = $this->actingAs($employee)->patch(route('tickets.finalize', $ticket));

        $response->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'estado' => 'finalizado',
        ]);

        Notification::assertSentTo(Cliente::query()->findOrFail($client->id), TicketFinalizedDatabaseNotification::class);
        Notification::assertNotSentTo($employee, TicketFinalizedDatabaseNotification::class);
    }
}
