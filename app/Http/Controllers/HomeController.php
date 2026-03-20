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
use Illuminate\Support\Facades\Storage;
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
        $ticketsBase = $this->ticketsQueryForCurrentUser();
        $ticketsWithDeleted = $this->ticketsQueryForCurrentUser(includeDeleted: true);

        $statusCounts = (clone $ticketsBase)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $closedCount = (clone $ticketsWithDeleted)->onlyTrashed()->count();

        $chartLabels = [];
        $chartValues = [];
        $chartColors = [];

        foreach (array_keys($statusConfig) as $state) {
            $chartLabels[] = $statusConfig[$state]['label'];
            $chartValues[] = $state === 'cerrado'
                ? $closedCount
                : (int) ($statusCounts[$state] ?? 0);
            $chartColors[] = $statusConfig[$state]['color'];
        }

        $stats = [
            'total_usuarios' => User::count(),
            'total_clientes' => Cliente::count(),
            'total_empleados' => Empleado::count(),
            'total_departamentos' => Departamento::count(),
            'total_tickets' => (clone $ticketsBase)->count(),
            'pendientes' => (int) ($statusCounts['pendiente'] ?? 0),
            'en_proceso' => (int) ($statusCounts['en_proceso'] ?? 0),
            'finalizado' => (int) ($statusCounts['finalizado'] ?? 0),
            'cerrado' => $closedCount,
        ];

        return view('home', [
            'stats' => $stats,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'chartColors' => $chartColors,
            'menuBadges' => $this->menuBadges(),
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles(['Usuario']);

        return back()->with('success', 'Usuario agregado correctamente.');
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

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroyUsuario(User $user): RedirectResponse
    {
        if ((int) auth()->id() === (int) $user->id) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
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
            'email' => ['required', 'email', 'max:255', 'unique:clientes,email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'empresa' => ['nullable', 'string', 'max:120'],
        ]);

        $usuario = User::create([
            'name' => trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? '')),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $usuario->syncRoles(['Usuario']);

        Cliente::create($validated + ['activo' => true]);

        return back()->with('success', 'Cliente agregado correctamente.');
    }

    public function updateCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        $linkedUser = User::where('email', $cliente->email)->first();

        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($cliente->id), Rule::unique('users', 'email')->ignore($linkedUser?->id)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'empresa' => ['nullable', 'string', 'max:120'],
        ]);

        if ($linkedUser) {
            $linkedUser->name = trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? ''));
            $linkedUser->email = $validated['email'];
            $linkedUser->password = Hash::make($validated['password']);
            $linkedUser->save();
            $linkedUser->syncRoles(['Usuario']);
        } else {
            $newUser = User::create([
                'name' => trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? '')),
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $newUser->syncRoles(['Usuario']);
        }

        $cliente->update($validated);

        return back()->with('success', 'Cliente actualizado correctamente.');
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
            'email' => ['required', 'email', 'max:255', 'unique:empleados,email', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? '')),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->syncRoles(['Empleado']);

        Empleado::create([
            'user_id' => $user->id,
            'departamento_id' => $validated['departamento_id'],
            'nombres' => $validated['nombres'],
            'segundo_nombre' => $validated['segundo_nombre'] ?? null,
            'apellidos' => $validated['apellidos'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
            'activo' => true,
        ]);

        return back()->with('success', 'Empleado agregado correctamente.');
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
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $empleado->update([
            'departamento_id' => $validated['departamento_id'],
            'nombres' => $validated['nombres'],
            'segundo_nombre' => $validated['segundo_nombre'] ?? null,
            'apellidos' => $validated['apellidos'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
        ]);

        if ($empleado->user_id) {
            $user = User::find($empleado->user_id);
            if ($user) {
                $user->name = trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? ''));
                $user->email = $validated['email'];
                if (!empty($validated['password'])) {
                    $user->password = Hash::make($validated['password']);
                }
                $user->save();
                $user->syncRoles(['Empleado']);
            }
        }

        return back()->with('success', 'Empleado actualizado correctamente.');
    }

    public function destroyEmpleado(Empleado $empleado): RedirectResponse
    {
        try {
            $userId = $empleado->user_id;
            $empleado->delete();

            if ($userId) {
                User::whereKey($userId)->delete();
            }
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

        return back()->with('success', 'Departamento actualizado correctamente.');
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
        $tickets = $this->ticketsQueryForCurrentUser()
            ->with(['cliente', 'empleado', 'departamento'])
            ->latest()
            ->get();

        $currentEmployee = null;
        if (auth()->user()->hasRole('Empleado')) {
            $currentEmployee = Empleado::where('user_id', auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();
        }

        return view('tickets.index', [
            'tickets' => $tickets,
            'clientes' => Cliente::orderBy('nombres')->orderBy('apellidos')->get(),
            'empleados' => Empleado::orderBy('nombres')->orderBy('apellidos')->get(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'departamentosActivos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'currentEmployeeId' => $currentEmployee?->id,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function showTicket(Ticket $ticket)
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        $this->markTicketInProgressWhenEmployeeEnters($ticket);

        $ticket->load(['cliente', 'empleado', 'departamento']);
        $messages = $ticket->mensajes()
            ->with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values();

        return view('tickets.show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => ['nullable', 'string', 'max:25', Rule::unique('tickets', 'codigo')],
            'departamento_id' => ['required', Rule::exists('departamentos', 'id')->where(fn ($query) => $query->where('activo', true))],
            'asunto' => ['required', 'string', 'max:180'],
            'descripcion' => ['required', 'string'],
            'prioridad' => ['required', Rule::in(['baja', 'media', 'alta'])],
        ]);

        $currentUser = auth()->user();
        $cliente = Cliente::where('email', $currentUser->email)->first();

        if (!$cliente) {
            $cliente = Cliente::create([
                'nombres' => $currentUser->name,
                'apellidos' => '',
                'email' => $currentUser->email,
                'activo' => true,
            ]);
        }

        $validated['cliente_id'] = $cliente->id;
        $validated['empleado_id'] = null;

        if ($currentUser->hasRole('Empleado')) {
            $empleado = Empleado::where('user_id', $currentUser->id)
                ->orWhere('email', $currentUser->email)
                ->first();

            if ($empleado) {
                $validated['empleado_id'] = $empleado->id;
            }
        }

        if (empty($validated['codigo'])) {
            $validated['codigo'] = $this->nextTicketCode();
        }

        $validated['estado'] = 'pendiente';
        $validated['fecha_cierre'] = null;

        $ticket = Ticket::create($validated);

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => $validated['descripcion'],
            'tipo' => 'creacion',
        ]);

        return back()->with('success', 'Ticket agregado correctamente.');
    }

    public function attendTicket(Ticket $ticket): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        $employee = Empleado::where('user_id', auth()->id())
            ->orWhere('email', auth()->user()->email)
            ->first();

        if ($employee) {
            $ticket->empleado_id = $employee->id;
        }

        $ticket->estado = 'en_proceso';
        $ticket->save();

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => 'Ticket atendido por ' . auth()->user()->name . '.',
            'tipo' => 'atencion',
        ]);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket atendido correctamente.');
    }

    public function storeTicketMessage(Request $request, Ticket $ticket): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if ($ticket->estado === 'finalizado') {
            return back()->with('error', 'Este ticket ya fue finalizado y no admite mas comentarios.');
        }

        $validated = $request->validate([
            'mensaje' => ['nullable', 'string', 'max:3000'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if (empty($validated['mensaje']) && !$request->hasFile('imagen')) {
            return back()->with('error', 'Debes escribir un mensaje o subir una imagen.');
        }

        $payload = [
            'user_id' => auth()->id(),
            'mensaje' => $validated['mensaje'] ?? '',
            'tipo' => 'comentario',
        ];

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $path = $file->store('ticket-mensajes', 'public');

            $payload['imagen_path'] = $path;
            $payload['imagen_nombre'] = $file->getClientOriginalName();
            $payload['imagen_mime'] = $file->getClientMimeType();
            $payload['imagen_size'] = $file->getSize();
        }

        $ticket->mensajes()->create($payload);

        return back()->with('success', 'Mensaje enviado correctamente.');
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

        return back()->with('success', 'Ticket actualizado correctamente.');
    }

    public function destroyTicket(Ticket $ticket): RedirectResponse
    {
        if (!$this->canDeleteTicket($ticket)) {
            abort(403);
        }

        foreach ($ticket->mensajes as $mensaje) {
            if ($mensaje->imagen_path) {
                Storage::disk('public')->delete($mensaje->imagen_path);
            }
        }

        $ticket->estado = 'cerrado';
        $ticket->fecha_cierre = now();
        $ticket->save();
        $ticket->delete();

        return back()->with('success', 'Ticket eliminado correctamente.');
    }

    public function finalizeTicket(Ticket $ticket): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!$this->canFinalizeTicket($ticket)) {
            abort(403);
        }

        $ticket->estado = 'finalizado';
        $ticket->fecha_cierre = now();
        $ticket->save();

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => 'Ticket finalizado por ' . auth()->user()->name . '.',
            'tipo' => 'atencion',
        ]);

        return back()->with('success', 'Ticket finalizado correctamente.');
    }

    private function ticketsQueryForCurrentUser(bool $includeDeleted = false)
    {
        $query = Ticket::query();

        if ($includeDeleted) {
            $query->withTrashed();
        }

        if (auth()->check() && auth()->user()->hasRole('Usuario')) {
            $query->whereHas('cliente', function ($q): void {
                $q->where('email', auth()->user()->email);
            });
        }

        if (auth()->check() && auth()->user()->hasRole('Empleado')) {
            $employee = Empleado::where('user_id', auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();

            if ($employee) {
                $query->where(function ($q) use ($employee): void {
                    $q->where('empleado_id', $employee->id)
                      ->orWhere(function ($q2): void {
                          $q2->whereNull('empleado_id')->where('estado', 'pendiente');
                      });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
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

    private function canAccessTicket(Ticket $ticket): bool
    {
        if (auth()->user()->hasRole('Administrador')) {
            return true;
        }

        return $this->ticketsQueryForCurrentUser()
            ->whereKey($ticket->id)
            ->exists();
    }

    private function canDeleteTicket(Ticket $ticket): bool
    {
        if (auth()->user()->hasRole('Administrador')) {
            return true;
        }

        if (auth()->user()->hasRole('Empleado')) {
            $employee = Empleado::where('user_id', auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();

            if (!$employee) {
                return false;
            }

            return (int) $ticket->empleado_id === (int) $employee->id;
        }

        if (auth()->user()->hasRole('Usuario')) {
            return $ticket->cliente && $ticket->cliente->email === auth()->user()->email;
        }

        return false;
    }

    private function canFinalizeTicket(Ticket $ticket): bool
    {
        if (!auth()->user()->hasRole('Empleado')) {
            return false;
        }

        $employee = Empleado::where('user_id', auth()->id())
            ->orWhere('email', auth()->user()->email)
            ->first();

        if (!$employee) {
            return false;
        }

        return (int) $ticket->empleado_id === (int) $employee->id
            && in_array($ticket->estado, ['pendiente', 'en_proceso'], true);
    }

    private function markTicketInProgressWhenEmployeeEnters(Ticket $ticket): void
    {
        if (!auth()->user()->hasRole('Empleado') || $ticket->estado !== 'pendiente') {
            return;
        }

        $employee = Empleado::where('user_id', auth()->id())
            ->orWhere('email', auth()->user()->email)
            ->first();

        if (!$employee) {
            return;
        }

        $shouldUpdate = is_null($ticket->empleado_id) || (int) $ticket->empleado_id === (int) $employee->id;

        if (!$shouldUpdate) {
            return;
        }

        $ticket->empleado_id = $employee->id;
        $ticket->estado = 'en_proceso';
        $ticket->save();

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => 'Ticket atendido por ' . auth()->user()->name . '.',
            'tipo' => 'atencion',
        ]);
    }
}
