<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $statusConfig = config('adminlte.ticket_states', []);
        $statusCounts = $this->ticketStatusCounts();

        $chartLabels = [];
        $chartValues = [];
        $chartColors = [];

        foreach (array_keys($statusConfig) as $state) {
            $chartLabels[] = $statusConfig[$state]['label'];
            $chartValues[] = (int) ($statusCounts[$state] ?? 0);
            $chartColors[] = $statusConfig[$state]['color'];
        }

        $stats = [
            'total_usuarios' => User::count(),
            'total_clientes' => Cliente::count(),
            'total_empleados' => Empleado::count(),
            'total_departamentos' => Departamento::count(),
            'total_tickets' => Ticket::count(),
            'pendientes' => (int) ($statusCounts['pendiente'] ?? 0),
            'en_proceso' => (int) ($statusCounts['en_proceso'] ?? 0),
            'finalizado' => (int) ($statusCounts['finalizado'] ?? 0),
            'cerrado' => (int) ($statusCounts['cerrado'] ?? 0),
        ];

        return view('home', [
            'stats' => $stats,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'chartColors' => $chartColors,
            'menuBadges' => $this->menuBadges($statusCounts),
        ]);
    }

    public function usuarios()
    {
        return view('usuarios.index', [
            'usuarios' => User::latest()->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeUsuario(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Usuario agregado correctamente.');
    }

    public function editUsuario(User $user)
    {
        return view('usuarios.edit', [
            'usuario' => $user,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function updateUsuario(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroyUsuario(User $user): RedirectResponse
    {
        if ((int) auth()->id() === (int) $user->id) {
            return back()->with('success', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function clientes()
    {
        return view('clientes.index', [
            'clientes' => Cliente::latest()->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeCliente(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:clientes,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'empresa' => ['nullable', 'string', 'max:120'],
        ]);

        Cliente::create($validated + ['activo' => true]);

        return back()->with('success', 'Cliente agregado correctamente.');
    }

    public function editCliente(Cliente $cliente)
    {
        return view('clientes.edit', [
            'cliente' => $cliente,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function updateCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($cliente->id)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'empresa' => ['nullable', 'string', 'max:120'],
        ]);

        $cliente->update($validated);

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroyCliente(Cliente $cliente): RedirectResponse
    {
        try {
            $cliente->delete();
        } catch (QueryException $exception) {
            return back()->with('error', 'No se puede eliminar el cliente porque tiene registros relacionados.');
        }

        return back()->with('success', 'Cliente eliminado correctamente.');
    }

    public function empleados()
    {
        return view('empleados.index', [
            'empleados' => Empleado::with('departamento')->latest()->get(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeEmpleado(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:empleados,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
        ]);

        Empleado::create($validated + ['activo' => true]);

        return back()->with('success', 'Empleado agregado correctamente.');
    }

    public function editEmpleado(Empleado $empleado)
    {
        return view('empleados.edit', [
            'empleado' => $empleado,
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function updateEmpleado(Request $request, Empleado $empleado): RedirectResponse
    {
        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('empleados', 'email')->ignore($empleado->id)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
        ]);

        $empleado->update($validated);

        return redirect()->route('empleados.index')->with('success', 'Empleado actualizado correctamente.');
    }

    public function destroyEmpleado(Empleado $empleado): RedirectResponse
    {
        try {
            $empleado->delete();
        } catch (QueryException $exception) {
            return back()->with('error', 'No se puede eliminar el empleado porque tiene registros relacionados.');
        }

        return back()->with('success', 'Empleado eliminado correctamente.');
    }

    public function departamentos()
    {
        return view('departamentos.index', [
            'departamentos' => Departamento::latest()->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeDepartamento(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120', 'unique:departamentos,nombre'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        Departamento::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activo' => (bool) ($validated['activo'] ?? true),
        ]);

        return back()->with('success', 'Departamento agregado correctamente.');
    }

    public function editDepartamento(Departamento $departamento)
    {
        return view('departamentos.edit', [
            'departamento' => $departamento,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function updateDepartamento(Request $request, Departamento $departamento): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120', Rule::unique('departamentos', 'nombre')->ignore($departamento->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $departamento->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activo' => (bool) ($validated['activo'] ?? false),
        ]);

        return redirect()->route('departamentos.index')->with('success', 'Departamento actualizado correctamente.');
    }

    public function destroyDepartamento(Departamento $departamento): RedirectResponse
    {
        try {
            $departamento->delete();
        } catch (QueryException $exception) {
            return back()->with('error', 'No se puede eliminar el departamento porque tiene registros relacionados.');
        }

        return back()->with('success', 'Departamento eliminado correctamente.');
    }

    public function tickets()
    {
        return view('tickets.index', [
            'tickets' => Ticket::with(['cliente', 'empleado', 'departamento'])->latest()->get(),
            'clientes' => Cliente::orderBy('nombres')->orderBy('apellidos')->get(),
            'empleados' => Empleado::orderBy('nombres')->orderBy('apellidos')->get(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => ['nullable', 'string', 'max:25', Rule::unique('tickets', 'codigo')],
            'cliente_id' => ['required', 'exists:clientes,id'],
            'empleado_id' => ['nullable', 'exists:empleados,id'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'asunto' => ['required', 'string', 'max:180'],
            'descripcion' => ['required', 'string'],
            'estado' => ['required', Rule::in(['pendiente', 'en_proceso', 'finalizado', 'cerrado'])],
            'prioridad' => ['required', Rule::in(['baja', 'media', 'alta'])],
        ]);

        if (empty($validated['codigo'])) {
            $validated['codigo'] = $this->nextTicketCode();
        }

        Ticket::create($validated);

        return back()->with('success', 'Ticket agregado correctamente.');
    }

    public function editTicket(Ticket $ticket)
    {
        return view('tickets.edit', [
            'ticket' => $ticket,
            'clientes' => Cliente::orderBy('nombres')->orderBy('apellidos')->get(),
            'empleados' => Empleado::orderBy('nombres')->orderBy('apellidos')->get(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function updateTicket(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:25', Rule::unique('tickets', 'codigo')->ignore($ticket->id)],
            'cliente_id' => ['required', 'exists:clientes,id'],
            'empleado_id' => ['nullable', 'exists:empleados,id'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'asunto' => ['required', 'string', 'max:180'],
            'descripcion' => ['required', 'string'],
            'estado' => ['required', Rule::in(['pendiente', 'en_proceso', 'finalizado', 'cerrado'])],
            'prioridad' => ['required', Rule::in(['baja', 'media', 'alta'])],
        ]);

        $ticket->update($validated);

        return redirect()->route('tickets.index')->with('success', 'Ticket actualizado correctamente.');
    }

    public function destroyTicket(Ticket $ticket): RedirectResponse
    {
        $ticket->delete();

        return back()->with('success', 'Ticket eliminado correctamente.');
    }

    private function ticketStatusCounts()
    {
        return Ticket::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');
    }

    private function menuBadges($statusCounts = null): array
    {
        $counts = $statusCounts ?? $this->ticketStatusCounts();

        return [
            'pendientes' => (int) ($counts['pendiente'] ?? 0),
        ];
    }

    private function nextTicketCode(): string
    {
        $lastId = (int) Ticket::max('id');

        return 'TCK-' . str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);
    }
}
