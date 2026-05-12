<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PendingTicketDatabaseNotification;
use App\Services\TicketNotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_configured_email_disables_mail_but_keeps_system_notifications(): void
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
            'email' => 'empleado@example.com',
            'activo' => true,
            'departamento_id' => $departamento->id,
        ]);
        $employee->assignRole('Empleado');
        $employee->departamentos()->sync([$departamento->id]);

        $admin = User::factory()->create([
            'name' => 'Admin Helpdesk',
            'email' => 'admin@example.com',
            'activo' => true,
        ]);
        $admin->assignRole('Administrador');

        $client = User::factory()->create([
            'name' => 'Cliente Demo',
            'activo' => true,
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

        $sentCount = app(TicketNotificationService::class)->notifyTicketCreated($ticket);

        $this->assertSame(2, $sentCount);
        Mail::assertNothingSent();
        Notification::assertSentTo($employee, PendingTicketDatabaseNotification::class);
        Notification::assertSentTo($admin, PendingTicketDatabaseNotification::class);
    }

    public function test_creating_ticket_notifies_department_employee_and_admin_immediately(): void
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
            'email' => 'empleado@example.com',
            'activo' => true,
            'departamento_id' => $departamento->id,
        ]);
        $employee->assignRole('Empleado');
        $employee->departamentos()->sync([$departamento->id]);

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'activo' => true,
        ]);
        $admin->assignRole('Administrador');

        $client = User::factory()->create([
            'activo' => true,
            'departamento_id' => $departamento->id,
        ]);
        $client->assignRole('Usuario');

        $response = $this->actingAs($client)->post(route('tickets.store'), [
            'asunto' => 'No enciende el equipo',
            'descripcion' => 'El equipo no inicia.',
        ]);

        $response->assertRedirect();

        Notification::assertSentTo($employee, PendingTicketDatabaseNotification::class);
        Notification::assertSentTo($admin, PendingTicketDatabaseNotification::class);
        Notification::assertNotSentTo($client, PendingTicketDatabaseNotification::class);
    }
}
