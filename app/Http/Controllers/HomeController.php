<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Ticket;
use App\Models\TicketRemoteSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        $recentTickets = (clone $ticketsBase)
            ->with(['departamento'])
            ->latest()
            ->limit(6)
            ->get();

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
            'recentTickets' => $recentTickets,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function usuarios(Request $request)
    {
        $query = User::latest();
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $perPage = $this->resolvePerPage($request);

        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%');
        }

        $usuarios = $query->paginate($perPage)->withQueryString();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'searchQuery' => $search,
            'perPage' => $perPage,
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

    public function clientes(Request $request)
    {
        $query = Cliente::latest();
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $perPage = $this->resolvePerPage($request);

        if ($search !== '') {
            $query->where('nombres', 'like', '%' . $search . '%')
                ->orWhere('apellidos', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('telefono', 'like', '%' . $search . '%')
                ->orWhere('empresa', 'like', '%' . $search . '%');
        }

        $clientes = $query->paginate($perPage)->withQueryString();

        return view('clientes.index', [
            'clientes' => $clientes,
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function reviewCliente(Request $request, Cliente $cliente)
    {
        [$period, $fromInput, $toInput, $fromDate, $toDate] = $this->resolveReviewRange($request);
        $perPage = $this->resolvePerPage($request);

        $baseQuery = Ticket::withTrashed()
            ->with(['empleado', 'departamento'])
            ->where('cliente_id', $cliente->id)
            ->whereBetween('created_at', [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()]);

        $tickets = (clone $baseQuery)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total_tickets' => (clone $baseQuery)->count(),
            'empleados_distintos' => (clone $baseQuery)
                ->whereNotNull('empleado_id')
                ->distinct('empleado_id')
                ->count('empleado_id'),
            'tickets_cerrados' => (clone $baseQuery)->where('estado', 'finalizado')->count(),
            'tickets_eliminados' => (clone $baseQuery)->onlyTrashed()->count(),
        ];

        return view('clientes.review', [
            'cliente' => $cliente,
            'tickets' => $tickets,
            'summary' => $summary,
            'period' => $period,
            'fromInput' => $fromInput,
            'toInput' => $toInput,
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

    public function empleados(Request $request)
    {
        $query = Empleado::with(['departamento', 'departamentos'])->latest();
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $perPage = $this->resolvePerPage($request);

        if ($search !== '') {
            $query->where('nombres', 'like', '%' . $search . '%')
                ->orWhere('apellidos', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('telefono', 'like', '%' . $search . '%')
                ->orWhere('cargo', 'like', '%' . $search . '%');
        }

        $empleados = $query->paginate($perPage)->withQueryString();

        return view('empleados.index', [
            'empleados' => $empleados,
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function reviewEmpleado(Request $request, Empleado $empleado)
    {
        [$period, $fromInput, $toInput, $fromDate, $toDate] = $this->resolveReviewRange($request);
        $perPage = $this->resolvePerPage($request);

        $empleado->loadMissing(['departamentos', 'departamento']);

        $baseQuery = Ticket::withTrashed()
            ->with(['cliente', 'departamento'])
            ->where('empleado_id', $empleado->id)
            ->whereBetween('created_at', [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()]);

        $tickets = (clone $baseQuery)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total_tickets' => (clone $baseQuery)->count(),
            'clientes_atendidos' => (clone $baseQuery)
                ->whereNotNull('cliente_id')
                ->distinct('cliente_id')
                ->count('cliente_id'),
            'tickets_cerrados' => (clone $baseQuery)->where('estado', 'finalizado')->count(),
            'tickets_eliminados' => (clone $baseQuery)->onlyTrashed()->count(),
        ];

        return view('empleados.review', [
            'empleado' => $empleado,
            'tickets' => $tickets,
            'summary' => $summary,
            'period' => $period,
            'fromInput' => $fromInput,
            'toInput' => $toInput,
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
            'departamento_id' => ['nullable', 'exists:departamentos,id'],
            'departamento_ids' => ['nullable', 'array'],
            'departamento_ids.*' => ['integer', 'exists:departamentos,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $selectedDepartmentIds = collect($validated['departamento_ids'] ?? [])
            ->map(static fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedDepartmentIds->isEmpty() && !empty($validated['departamento_id'])) {
            $selectedDepartmentIds->push((int) $validated['departamento_id']);
        }

        if ($selectedDepartmentIds->isEmpty()) {
            return back()
                ->withErrors(['departamento_ids' => 'Debes seleccionar al menos un departamento.'])
                ->withInput();
        }

        $user = User::create([
            'name' => trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? '')),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->syncRoles(['Empleado']);

        $empleado = Empleado::create([
            'user_id' => $user->id,
            'departamento_id' => $selectedDepartmentIds->first(),
            'nombres' => $validated['nombres'],
            'segundo_nombre' => $validated['segundo_nombre'] ?? null,
            'apellidos' => $validated['apellidos'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
            'activo' => true,
        ]);
        $empleado->departamentos()->sync($selectedDepartmentIds->all());

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
            'departamento_id' => ['nullable', 'exists:departamentos,id'],
            'departamento_ids' => ['nullable', 'array'],
            'departamento_ids.*' => ['integer', 'exists:departamentos,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
        $selectedDepartmentIds = collect($validated['departamento_ids'] ?? [])
            ->map(static fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedDepartmentIds->isEmpty() && !empty($validated['departamento_id'])) {
            $selectedDepartmentIds->push((int) $validated['departamento_id']);
        }

        if ($selectedDepartmentIds->isEmpty()) {
            return back()
                ->withErrors(['departamento_ids' => 'Debes seleccionar al menos un departamento.'])
                ->withInput();
        }

        $empleado->update([
            'departamento_id' => $selectedDepartmentIds->first(),
            'nombres' => $validated['nombres'],
            'segundo_nombre' => $validated['segundo_nombre'] ?? null,
            'apellidos' => $validated['apellidos'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
        ]);
        $empleado->departamentos()->sync($selectedDepartmentIds->all());

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

    public function departamentos(Request $request)
    {
        $query = Departamento::latest();
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $perPage = $this->resolvePerPage($request);

        if ($search !== '') {
            $query->where('nombre', 'like', '%' . $search . '%')
                ->orWhere('descripcion', 'like', '%' . $search . '%');
        }

        $departamentos = $query->paginate($perPage)->withQueryString();

        return view('departamentos.index', [
            'departamentos' => $departamentos,
            'searchQuery' => $search,
            'perPage' => $perPage,
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

    public function tickets(Request $request)
    {
        $query = $this->ticketsQueryForCurrentUser()
            ->with(['cliente', 'empleado', 'departamento']);
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $perPage = $this->resolvePerPage($request);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', '%' . $search . '%')
                    ->orWhere('asunto', 'like', '%' . $search . '%')
                    ->orWhere('descripcion', 'like', '%' . $search . '%')
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombres', 'like', '%' . $search . '%')
                            ->orWhere('apellidos', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('empleado', function ($q2) use ($search) {
                        $q2->where('nombres', 'like', '%' . $search . '%')
                            ->orWhere('apellidos', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        $tickets = $query->latest()->paginate($perPage)->withQueryString();

        $currentEmployee = null;
        if (auth()->user()->hasRole('Empleado')) {
            $currentEmployee = Empleado::where('user_id', auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();
        }

        return view('tickets.index', [
            'tickets' => $tickets,
            'clientes' => Cliente::orderBy('nombres')->orderBy('apellidos')->get(),
            'empleados' => Empleado::with('departamentos')->orderBy('nombres')->orderBy('apellidos')->get(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'departamentosActivos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'currentEmployeeId' => $currentEmployee?->id,
            'nextTicketCode' => $this->nextTicketCode(),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function showTicket(Ticket $ticket)
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        $ticket->load(['cliente', 'empleado', 'departamento']);
        $messages = $ticket->mensajes()
            ->with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values();
        $remoteEnabled = Schema::hasTable('ticket_remote_sessions');
        $remoteSession = $remoteEnabled
            ? $ticket->remoteSessions()->latest('id')->first()
            : null;

        return view('tickets.show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'remoteEnabled' => $remoteEnabled,
            'remoteSession' => $remoteSession,
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function requestRemoteSession(Ticket $ticket): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!Schema::hasTable('ticket_remote_sessions')) {
            return back()->with('error', 'La funcionalidad de soporte remoto aun no esta disponible.');
        }

        if (!$this->isAssignedEmployeeForTicket($ticket)) {
            abort(403, 'Solo el empleado asignado puede iniciar la conexion remota.');
        }

        if ($ticket->estado !== 'en_proceso') {
            return back()->with('error', 'La conexion remota solo se puede solicitar cuando el ticket esta en proceso.');
        }

        $hasActive = $ticket->remoteSessions()
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($hasActive) {
            return back()->with('error', 'Ya existe una solicitud remota activa para este ticket.');
        }

        TicketRemoteSession::create([
            'ticket_id' => $ticket->id,
            'requested_by_user_id' => auth()->id(),
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Solicitud remota enviada al cliente.');
    }

    public function updateRemoteSession(Request $request, Ticket $ticket, TicketRemoteSession $remoteSession): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!Schema::hasTable('ticket_remote_sessions')) {
            return back()->with('error', 'La funcionalidad de soporte remoto aun no esta disponible.');
        }

        if ((int) $remoteSession->ticket_id !== (int) $ticket->id) {
            abort(404);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject', 'share_code', 'end', 'signal_closed'])],
            'support_code' => ['nullable', 'string', 'max:40'],
        ]);

        $action = $validated['action'];

        if ($action === 'accept') {
            if (!$this->isTicketClientOwner($ticket)) {
                abort(403);
            }
            if ($remoteSession->status !== 'pending') {
                return back()->with('error', 'La solicitud remota ya no esta pendiente.');
            }

            $remoteSession->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            return back()->with('success', 'Solicitud remota aceptada correctamente.');
        }

        if ($action === 'reject') {
            if (!$this->isTicketClientOwner($ticket)) {
                abort(403);
            }
            if ($remoteSession->status !== 'pending') {
                return back()->with('error', 'La solicitud remota ya no esta pendiente.');
            }

            $remoteSession->update([
                'status' => 'rejected',
                'responded_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
            ]);

            return back()->with('success', 'Solicitud remota rechazada.');
        }

        if ($action === 'share_code') {
            if (!$this->isTicketClientOwner($ticket)) {
                abort(403);
            }
            if ($remoteSession->status !== 'accepted') {
                return back()->with('error', 'Debes aceptar la solicitud antes de compartir el codigo.');
            }

            $supportCode = trim((string) ($validated['support_code'] ?? ''));
            if ($supportCode === '') {
                return back()->with('error', 'Debes ingresar el codigo de AnyDesk.');
            }

            $remoteSession->update([
                'support_code' => $supportCode,
            ]);

            return back()->with('success', 'Codigo de AnyDesk compartido.');
        }

        if ($action === 'end') {
            if (!$this->isAssignedEmployeeForTicket($ticket)) {
                abort(403);
            }
            if (!in_array($remoteSession->status, ['accepted', 'pending'], true)) {
                return back()->with('error', 'No hay una sesion remota activa para finalizar.');
            }

            $remoteSession->update([
                'status' => 'ended',
                'ended_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
            ]);

            return back()->with('success', 'Conexion remota finalizada correctamente.');
        }

        if (!$this->isTicketClientOwner($ticket) && !$this->isAssignedEmployeeForTicket($ticket)) {
            abort(403);
        }

        if (!in_array($remoteSession->status, ['accepted', 'pending'], true)) {
            return back()->with('error', 'No hay una sesion remota activa para cerrar.');
        }

        $remoteSession->update([
            'status' => 'ended',
            'ended_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'note' => 'Cierre informado desde la interfaz.',
        ]);

        return back()->with('success', 'Se marco la sesion remota como finalizada.');
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

        // Firehose-safe dedupe in case another user just created a ticket
        while (Ticket::where('codigo', $validated['codigo'])->exists()) {
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

        if ($ticket->estado !== 'pendiente') {
            return back()->with('error', 'Solo se pueden atender tickets en estado pendiente.');
        }

        $currentUser = auth()->user();

        if ($currentUser->hasRole('Administrador')) {
            $adminEmployee = Empleado::where('user_id', $currentUser->id)
                ->orWhere('email', $currentUser->email)
                ->first();

            if ($adminEmployee) {
                $ticket->empleado_id = $adminEmployee->id;
            }

            $ticket->estado = 'en_proceso';
            $ticket->save();

            $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => 'Ticket atendido por ' . $currentUser->name . '.',
                'tipo' => 'atencion',
            ]);

            return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket atendido correctamente.');
        }

        $employee = Empleado::with('departamentos')->where('user_id', $currentUser->id)
            ->orWhere('email', $currentUser->email)
            ->first();

        if (!$employee || !$this->employeeBelongsToDepartment($employee, (int) $ticket->departamento_id)) {
            abort(403, 'No tienes acceso a este departamento.');
        }

        $ticket->empleado_id = $employee->id;
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
            'adjuntos' => ['nullable', 'array', 'max:5'],
            'adjuntos.*' => ['file', 'max:12288', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,7z,csv'],
            'adjunto' => ['nullable', 'file', 'max:12288', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,7z,csv'],
            'imagen' => ['nullable', 'file', 'max:12288', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,7z,csv'],
        ]);

        $files = collect($request->file('adjuntos', []));
        if ($request->hasFile('adjunto')) {
            $files->push($request->file('adjunto'));
        }
        if ($request->hasFile('imagen')) {
            $files->push($request->file('imagen'));
        }
        $files = $files->filter()->take(5)->values();

        if (empty($validated['mensaje']) && $files->isEmpty()) {
            return back()->with('error', 'Debes escribir un mensaje o subir un archivo.');
        }

        if ($files->isEmpty()) {
            $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => $validated['mensaje'] ?? '',
                'tipo' => 'comentario',
            ]);
            return back();
        }

        foreach ($files as $index => $file) {
            $path = $file->store('ticket-mensajes', 'public');

            $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => $index === 0 ? ($validated['mensaje'] ?? '') : '',
                'tipo' => 'comentario',
                'imagen_path' => $path,
                'imagen_nombre' => $file->getClientOriginalName(),
                'imagen_mime' => $file->getClientMimeType(),
                'imagen_size' => $file->getSize(),
            ]);
        }

        return back();
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

        if (!empty($validated['empleado_id'])) {
            $empleado = Empleado::with('departamentos')->find($validated['empleado_id']);

            if (!$empleado || !$this->employeeBelongsToDepartment($empleado, (int) $validated['departamento_id'])) {
                return back()
                    ->withErrors(['empleado_id' => 'El empleado seleccionado no pertenece al departamento del ticket.'])
                    ->withInput();
            }
        }

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
            $employee = Empleado::with('departamentos')->where('user_id', auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();

            if ($employee) {
                $departmentIds = $employee->departamentos->pluck('id')->toArray();
                if (empty($departmentIds) && !empty($employee->departamento_id)) {
                    $departmentIds = [(int) $employee->departamento_id];
                }
                $query->where(function ($q) use ($employee, $departmentIds): void {
                    $q->where('empleado_id', $employee->id)
                      ->orWhere(function ($q2) use ($departmentIds): void {
                          $q2->whereNull('empleado_id')
                              ->whereIn('departamento_id', $departmentIds)
                              ->where('estado', 'pendiente');
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

    public function nextTicketCodeJson()
    {
        return response()->json(['codigo' => $this->nextTicketCode()]);
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
        if (auth()->user()->hasRole('Administrador')) {
            return in_array($ticket->estado, ['pendiente', 'en_proceso'], true);
        }

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

    private function employeeBelongsToDepartment(Empleado $employee, int $departmentId): bool
    {
        $employee->loadMissing('departamentos');

        if ($employee->departamentos->contains('id', $departmentId)) {
            return true;
        }

        return (int) ($employee->departamento_id ?? 0) === $departmentId;
    }

    private function isAssignedEmployeeForTicket(Ticket $ticket): bool
    {
        if (auth()->user()->hasRole('Administrador')) {
            return true;
        }

        if (!auth()->user()->hasRole('Empleado')) {
            return false;
        }

        $employee = Empleado::where('user_id', auth()->id())
            ->orWhere('email', auth()->user()->email)
            ->first();

        if (!$employee) {
            return false;
        }

        return (int) $ticket->empleado_id === (int) $employee->id;
    }

    private function isTicketClientOwner(Ticket $ticket): bool
    {
        if (!auth()->user()->hasAnyRole(['Cliente', 'Usuario'])) {
            return false;
        }

        return ($ticket->cliente->email ?? null) === auth()->user()->email;
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', 10);

        return in_array($perPage, [10, 15], true) ? $perPage : 10;
    }

    private function resolveReviewRange(Request $request): array
    {
        $now = Carbon::now();
        $period = (string) $request->get('period', 'month');
        $allowedPeriods = ['week', 'month', 'year', 'custom'];

        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'month';
        }

        $fromInput = (string) $request->get('from', '');
        $toInput = (string) $request->get('to', '');

        if ($period === 'week') {
            $fromDate = $now->copy()->startOfWeek();
            $toDate = $now->copy()->endOfWeek();
        } elseif ($period === 'year') {
            $fromDate = $now->copy()->startOfYear();
            $toDate = $now->copy()->endOfYear();
        } elseif ($period === 'custom') {
            $fromDate = $this->safeParseDate($fromInput) ?? $now->copy()->startOfMonth();
            $toDate = $this->safeParseDate($toInput) ?? $now->copy()->endOfMonth();

            if ($fromDate->gt($toDate)) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
        } else {
            $fromDate = $now->copy()->startOfMonth();
            $toDate = $now->copy()->endOfMonth();
            $period = 'month';
        }

        if ($period !== 'custom') {
            $fromInput = $fromDate->toDateString();
            $toInput = $toDate->toDateString();
        }

        return [$period, $fromInput, $toInput, $fromDate, $toDate];
    }

    private function safeParseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $th) {
            return null;
        }
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
