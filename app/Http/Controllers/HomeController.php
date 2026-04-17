<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreClienteRequest;
use App\Http\Requests\Admin\StoreEmpleadoRequest;
use App\Http\Requests\Admin\UpdateClienteRequest;
use App\Http\Requests\Admin\UpdateEmpleadoRequest;
use App\Services\TicketNotificationService;
use App\Services\ReviewRangeService;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Ticket;
use App\Models\TicketMensaje;
use App\Models\TicketRemoteSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;
use Throwable;

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

        return view('usuarios.index', [
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

        return view('usuarios.review', [
            'cliente' => $cliente,
            'tickets' => $tickets,
            'summary' => $summary,
            'period' => $period,
            'fromInput' => $fromInput,
            'toInput' => $toInput,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeCliente(StoreClienteRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $usuario = User::create([
            'name' => trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? '')),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $usuario->syncRoles(['Usuario']);

        Cliente::create($validated + ['activo' => true]);

        return back()->with('success', 'Usuario agregado correctamente.');
    }

    public function updateCliente(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $linkedUser = User::where('email', $cliente->email)->first();

        $validated = $request->validated();

        if ($linkedUser) {
            $linkedUser->name = trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? ''));
            $linkedUser->email = $validated['email'];
            if (!empty($validated['password'])) {
                $linkedUser->password = Hash::make($validated['password']);
            }
            $linkedUser->save();
            $linkedUser->syncRoles(['Usuario']);
        } else {
            if (!empty($validated['password'])) {
                $newUser = User::create([
                    'name' => trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? '')),
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);
                $newUser->syncRoles(['Usuario']);
            }
        }

        $cliente->update($validated);

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroyCliente(Cliente $cliente): RedirectResponse
    {
        try {
            $cliente->delete();
        } catch (QueryException $exception) {
            return back()->with('error', 'No se puede eliminar el usuario porque tiene registros relacionados.');
        }

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleClienteCheckpoint(Cliente $cliente): RedirectResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $cliente->activo = !$cliente->activo;
        $cliente->save();

        return back()->with('success', $cliente->activo
            ? 'Usuario habilitado correctamente.'
            : 'Usuario deshabilitado correctamente.');
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
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'departamentosActivos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
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

    public function storeEmpleado(StoreEmpleadoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
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

    public function updateEmpleado(UpdateEmpleadoRequest $request, Empleado $empleado): RedirectResponse
    {
        $validated = $request->validated();
        $previousEmail = $empleado->email;
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

        try {
            DB::transaction(function () use ($empleado, $validated, $selectedDepartmentIds, $previousEmail): void {
                $updated = DB::table('empleados')
                    ->where('id', $empleado->id)
                    ->update([
                        'departamento_id' => $selectedDepartmentIds->first(),
                        'nombres' => $validated['nombres'],
                        'segundo_nombre' => $validated['segundo_nombre'] ?? null,
                        'apellidos' => $validated['apellidos'],
                        'email' => $validated['email'],
                        'telefono' => $validated['telefono'] ?? null,
                        'direccion' => $validated['direccion'] ?? null,
                        'cargo' => $validated['cargo'] ?? null,
                        'updated_at' => now(),
                    ]);

                if ($updated === 0) {
                    $currentEmail = DB::table('empleados')
                        ->where('id', $empleado->id)
                        ->value('email');

                    if ((string) $currentEmail !== (string) $validated['email']) {
                        throw new \RuntimeException('No se pudo actualizar el correo del empleado.');
                    }
                }

                $empleado->refresh();
                $empleado->departamentos()->sync($selectedDepartmentIds->all());

                $user = null;
                if ($empleado->user_id) {
                    $user = User::find($empleado->user_id);
                }
                if (!$user) {
                    $user = User::where('email', $previousEmail)->first();
                }

                if ($user) {
                    $user->name = trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? ''));
                    $user->email = $validated['email'];
                    if (!empty($validated['password'])) {
                        $user->password = Hash::make($validated['password']);
                    }
                    $user->save();
                    $user->syncRoles(['Empleado']);

                    if (empty($empleado->user_id) || (int) $empleado->user_id !== (int) $user->id) {
                        DB::table('empleados')
                            ->where('id', $empleado->id)
                            ->update([
                                'user_id' => $user->id,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

            return back()->with('success', 'Empleado actualizado correctamente.');
        } catch (\Throwable $e) {
            report($e);
            return back()
                ->withInput()
                ->with('error', 'No se pudo actualizar el empleado. Intenta nuevamente.');
        }
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

    public function toggleEmpleadoCheckpoint(Empleado $empleado): RedirectResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $empleado->activo = !$empleado->activo;
        $empleado->save();

        return back()->with('success', $empleado->activo
            ? 'Empleado habilitado correctamente.'
            : 'Empleado deshabilitado correctamente.');
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

    public function toggleDepartamentoCheckpoint(Departamento $departamento): RedirectResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $departamento->activo = !$departamento->activo;
        $departamento->save();

        return back()->with('success', $departamento->activo
            ? 'Departamento habilitado correctamente.'
            : 'Departamento deshabilitado correctamente.');
    }

    public function tickets(Request $request)
    {
        $currentUser = auth()->user();
        $isAdmin = $currentUser->hasRole('Administrador');
        $canCreateTickets = $currentUser->can('crear tickets');

        $query = $this->ticketsQueryForCurrentUser()
            ->with(['cliente', 'empleado', 'departamento']);
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $perPage = $this->resolvePerPage($request);
        $prioritizeRemoteActive = Schema::hasTable('ticket_remote_sessions');
        $activeRemoteTicketId = null;
        $pendingRemoteTicketId = null;
        $activeRemoteTicketIds = collect();
        $pendingRemoteTicketIds = collect();

        if (!$isAdmin) {
            $query->where('estado', '!=', 'cerrado');
        }

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

        if ($prioritizeRemoteActive) {
            if ($isAdmin) {
                $ticketIdsSubquery = (clone $query)->select('tickets.id');

                $activeRemoteTicketIds = TicketRemoteSession::query()
                    ->join('tickets', 'tickets.id', '=', 'ticket_remote_sessions.ticket_id')
                    ->where('ticket_remote_sessions.status', 'accepted')
                    ->whereNull('ticket_remote_sessions.ended_at')
                    ->where('tickets.estado', 'en_proceso')
                    ->whereIn('ticket_remote_sessions.ticket_id', $ticketIdsSubquery)
                    ->distinct()
                    ->pluck('ticket_remote_sessions.ticket_id');

                $pendingRemoteTicketIds = TicketRemoteSession::query()
                    ->join('tickets', 'tickets.id', '=', 'ticket_remote_sessions.ticket_id')
                    ->where('ticket_remote_sessions.status', 'pending')
                    ->where('tickets.estado', 'en_proceso')
                    ->whereIn('ticket_remote_sessions.ticket_id', $ticketIdsSubquery)
                    ->distinct()
                    ->pluck('ticket_remote_sessions.ticket_id');
            } else {
                $activeRemoteTicketId = TicketRemoteSession::query()
                    ->join('tickets', 'tickets.id', '=', 'ticket_remote_sessions.ticket_id')
                    ->where('ticket_remote_sessions.status', 'accepted')
                    ->whereNull('ticket_remote_sessions.ended_at')
                    ->where('tickets.estado', 'en_proceso')
                    ->whereIn('ticket_remote_sessions.ticket_id', (clone $query)->select('tickets.id'))
                    ->latest('ticket_remote_sessions.id')
                    ->value('ticket_remote_sessions.ticket_id');

                $pendingRemoteTicketId = TicketRemoteSession::query()
                    ->join('tickets', 'tickets.id', '=', 'ticket_remote_sessions.ticket_id')
                    ->where('ticket_remote_sessions.status', 'pending')
                    ->where('tickets.estado', 'en_proceso')
                    ->whereIn('ticket_remote_sessions.ticket_id', (clone $query)->select('tickets.id'))
                    ->latest('ticket_remote_sessions.id')
                    ->value('ticket_remote_sessions.ticket_id');

                if (!empty($activeRemoteTicketId)) {
                    $query->orderByRaw('CASE WHEN tickets.id = ? THEN 2 WHEN tickets.id = ? THEN 1 ELSE 0 END DESC', [
                        $activeRemoteTicketId,
                        $pendingRemoteTicketId ?? 0,
                    ]);
                } elseif (!empty($pendingRemoteTicketId)) {
                    $query->orderByRaw('CASE WHEN tickets.id = ? THEN 1 ELSE 0 END DESC', [$pendingRemoteTicketId]);
                }
            }
        }

        $tickets = $query->latest()->paginate($perPage)->withQueryString();

        $currentEmployee = null;
        if ($currentUser->hasRole('Empleado')) {
            $currentEmployee = Empleado::where('user_id', auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();
        }

        $viewData = [
            'tickets' => $tickets,
            'currentEmployeeId' => $currentEmployee?->id,
            'searchQuery' => $search,
            'perPage' => $perPage,
            'activeRemoteTicketId' => $activeRemoteTicketId,
            'pendingRemoteTicketId' => $pendingRemoteTicketId,
            'activeRemoteTicketIds' => $activeRemoteTicketIds,
            'pendingRemoteTicketIds' => $pendingRemoteTicketIds,
            'menuBadges' => $this->menuBadges(),
        ];

        if ($canCreateTickets) {
            $viewData['departamentosActivos'] = Departamento::where('activo', true)->orderBy('nombre')->get();
            $viewData['nextTicketCode'] = $this->nextTicketCode();
        }

        return view('tickets.index', $viewData);
    }

    public function editTicket(Ticket $ticket)
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        return view('tickets.edit', [
            'ticket' => $ticket,
            'clientes' => Cliente::where('activo', true)->orderBy('nombres')->orderBy('apellidos')->get(),
            'empleados' => Empleado::where('activo', true)->orderBy('nombres')->orderBy('apellidos')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
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
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function ticketLiveData(Request $request, Ticket $ticket): JsonResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        $sinceMessageId = max(0, (int) $request->query('since_message_id', 0));
        $chatTimezone = config('app.timezone', 'America/La_Paz');

        $messages = $ticket->mensajes()
            ->with('user:id,name')
            ->where('id', '>', $sinceMessageId)
            ->orderBy('id')
            ->limit(40)
            ->get();

        $messagePayload = $messages->map(function (TicketMensaje $mensaje) use ($ticket, $chatTimezone): array {
            $isImageAttachment = str_starts_with((string) ($mensaje->imagen_mime ?? ''), 'image/');

            return [
                'id' => (int) $mensaje->id,
                'user_name' => $mensaje->user->name ?? 'Sistema',
                'tipo' => (string) $mensaje->tipo,
                'created_at' => $mensaje->created_at?->copy()->setTimezone($chatTimezone)->format('d/m/Y H:i'),
                'is_own' => (int) ($mensaje->user_id ?? 0) === (int) auth()->id(),
                'mensaje' => (string) ($mensaje->mensaje ?? ''),
                'attachment' => $mensaje->imagen_path
                    ? [
                        'url' => route('tickets.attachments.show', [$ticket, $mensaje]),
                        'is_image' => $isImageAttachment,
                        'name' => $mensaje->imagen_nombre ?? 'Descargar archivo',
                    ]
                    : null,
            ];
        })->values();

        $remoteEnabled = Schema::hasTable('ticket_remote_sessions');
        $remoteSession = $remoteEnabled
            ? $ticket->remoteSessions()->latest('id')->first()
            : null;

        $ticket->loadMissing(['empleado']);

        return response()->json([
            'ok' => true,
            'messages' => $messagePayload,
            'latest_message_id' => (int) ($messages->max('id') ?? $sinceMessageId),
            'ticket' => [
                'id' => (int) $ticket->id,
                'estado' => (string) $ticket->estado,
                'empleado_id' => (int) ($ticket->empleado_id ?? 0),
                'empleado_nombre' => $ticket->empleado->nombre_completo ?? 'Sin asignar',
            ],
            'remote' => [
                'enabled' => $remoteEnabled,
                'id' => (int) ($remoteSession->id ?? 0),
                'status' => (string) ($remoteSession->status ?? ''),
                'support_code' => (string) ($remoteSession->support_code ?? ''),
            ],
        ]);
    }

    public function showTicketAttachment(Ticket $ticket, TicketMensaje $mensaje)
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if ((int) $mensaje->ticket_id !== (int) $ticket->id || empty($mensaje->imagen_path)) {
            abort(404);
        }

        $disk = $this->ticketAttachmentDisk();
        if (!Storage::disk($disk)->exists($mensaje->imagen_path)) {
            abort(404);
        }

        $isImage = str_starts_with((string) ($mensaje->imagen_mime ?? ''), 'image/');
        $safeName = $mensaje->imagen_nombre ?: basename($mensaje->imagen_path);

        return Storage::disk($disk)->response(
            $mensaje->imagen_path,
            $safeName,
            [
                'Content-Type' => $mensaje->imagen_mime ?: 'application/octet-stream',
                'Content-Disposition' => ($isImage ? 'inline' : 'attachment') . '; filename="' . addslashes($safeName) . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function requestRemoteSession(Ticket $ticket): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!Schema::hasTable('ticket_remote_sessions')) {
            return back()->with('error', 'La funcionalidad de soporte remoto aun no esta disponible.');
        }

        if (!auth()->user()->hasRole('Administrador') && !$this->isAssignedEmployeeForTicket($ticket)) {
            abort(403, 'Solo el empleado asignado o un administrador puede iniciar la conexion remota.');
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

        $employeeId = (int) ($ticket->empleado_id ?? 0);
        if ($employeeId > 0 && $this->hasActiveRemoteSessionForEmployee($employeeId)) {
            return back()->with('error', 'No puedes solicitar otra conexion remota hasta finalizar la actual.');
        }

        $clientId = (int) ($ticket->cliente_id ?? 0);
        if ($clientId > 0 && $this->hasActiveRemoteSessionForClient($clientId)) {
            return back()->with('error', 'El usuario ya tiene una conexion remota activa en otro ticket.');
        }

        TicketRemoteSession::create([
            'ticket_id' => $ticket->id,
            'requested_by_user_id' => auth()->id(),
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Solicitud remota enviada al usuario.');
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
            if (!auth()->user()->hasRole('Administrador') && !$this->isTicketClientOwner($ticket)) {
                abort(403);
            }
            if ($remoteSession->status !== 'pending') {
                return back()->with('error', 'La solicitud remota ya no esta pendiente.');
            }

            $employeeId = (int) ($ticket->empleado_id ?? 0);
            if ($employeeId > 0 && $this->hasActiveRemoteSessionForEmployee($employeeId, (int) $remoteSession->id)) {
                return back()->with('error', 'El empleado ya tiene otra conexion remota activa.');
            }

            $clientId = (int) ($ticket->cliente_id ?? 0);
            if ($clientId > 0 && $this->hasActiveRemoteSessionForClient($clientId, (int) $remoteSession->id)) {
                return back()->with('error', 'Ya tienes otra conexion remota activa. Finalizala antes de aceptar una nueva.');
            }

            $remoteSession->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            return back()->with('success', 'Solicitud remota aceptada correctamente.');
        }

        if ($action === 'reject') {
            if (!auth()->user()->hasRole('Administrador') && !$this->isTicketClientOwner($ticket)) {
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
            if (!auth()->user()->hasRole('Administrador') && !$this->isTicketClientOwner($ticket)) {
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
            if (!auth()->user()->hasRole('Administrador') && !$this->isAssignedEmployeeForTicket($ticket)) {
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

            $anyDeskClosed = $this->tryCloseAnyDeskSession();

            if ($anyDeskClosed) {
                return back()->with('success', 'Conexion remota finalizada correctamente y AnyDesk se cerro.');
            }

            return back()->with('error', 'Conexion remota finalizada en el sistema.');
        }

        if (
            !auth()->user()->hasRole('Administrador')
            && !$this->isTicketClientOwner($ticket)
            && !$this->isAssignedEmployeeForTicket($ticket)
        ) {
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

        $anyDeskClosed = $this->tryCloseAnyDeskSession();

        if ($anyDeskClosed) {
            return back()->with('success', 'Se marco la sesion remota como finalizada y AnyDesk se cerro.');
        }

        return back()->with('error', 'Se finalizo la sesion en el sistema.');
    }

    public function fetchRemoteSupportCode(Ticket $ticket, TicketRemoteSession $remoteSession): JsonResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!Schema::hasTable('ticket_remote_sessions')) {
            return response()->json(['message' => 'La funcionalidad de soporte remoto aun no esta disponible.'], 422);
        }

        if ((int) $remoteSession->ticket_id !== (int) $ticket->id) {
            abort(404);
        }

        if (!auth()->user()->hasRole('Administrador') && !$this->isTicketClientOwner($ticket)) {
            abort(403);
        }

        if ($remoteSession->status !== 'accepted') {
            return response()->json(['message' => 'Debes aceptar la solicitud antes de obtener el codigo.'], 422);
        }

        $supportCode = $this->resolveAnyDeskSupportCode();
        if ($supportCode === null) {
            return response()->json(['message' => 'No se pudo leer automaticamente el codigo de AnyDesk.'], 422);
        }

        $remoteSession->update([
            'support_code' => $supportCode,
        ]);

        return response()->json([
            'support_code' => $supportCode,
        ]);
    }

    public function storeTicket(Request $request, TicketNotificationService $ticketNotificationService): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => ['nullable', 'string', 'max:25', Rule::unique('tickets', 'codigo')],
            'departamento_id' => ['required', Rule::exists('departamentos', 'id')->where(fn ($query) => $query->where('activo', true))],
            'asunto' => ['required', 'string', 'max:180'],
            'descripcion' => ['required', 'string'],
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

        try {
            $ticketNotificationService->notifyTicketCreated($ticket);
        } catch (Throwable $exception) {
            report($exception);
        }

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

    public function storeTicketMessage(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if ($ticket->estado === 'finalizado') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Este ticket ya fue finalizado y no admite mas comentarios.'], 422);
            }

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
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Debes escribir un mensaje o subir un archivo.'], 422);
            }

            return back()->with('error', 'Debes escribir un mensaje o subir un archivo.');
        }

        $createdMessageIds = [];

        if ($files->isEmpty()) {
            $createdMessage = $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => $validated['mensaje'] ?? '',
                'tipo' => 'comentario',
            ]);

            $createdMessageIds[] = (int) $createdMessage->id;

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'latest_message_id' => max($createdMessageIds),
                ]);
            }

            return back();
        }

        foreach ($files as $index => $file) {
            $path = $file->store($this->ticketAttachmentDirectory($ticket), $this->ticketAttachmentDisk());

            $createdMessage = $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => $index === 0 ? ($validated['mensaje'] ?? '') : '',
                'tipo' => 'comentario',
                'imagen_path' => $path,
                'imagen_nombre' => $file->getClientOriginalName(),
                'imagen_mime' => $file->getClientMimeType(),
                'imagen_size' => $file->getSize(),
            ]);

            $createdMessageIds[] = (int) $createdMessage->id;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'latest_message_id' => max($createdMessageIds),
            ]);
        }

        return back();
    }

    public function updateTicket(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:25', Rule::unique('tickets', 'codigo')->ignore($ticket->id)],
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where(fn ($query) => $query->where('activo', true))],
            'empleado_id' => ['nullable', Rule::exists('empleados', 'id')->where(fn ($query) => $query->where('activo', true))],
            'departamento_id' => ['required', Rule::exists('departamentos', 'id')->where(fn ($query) => $query->where('activo', true))],
            'asunto' => ['required', 'string', 'max:180'],
            'descripcion' => ['required', 'string'],
            'estado' => ['required', Rule::in(['pendiente', 'en_proceso', 'finalizado', 'cerrado'])],
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

        if (in_array((string) ($validated['estado'] ?? ''), ['finalizado', 'cerrado'], true)) {
            $this->closeActiveRemoteSessionsForTicket($ticket, 'La sesion remota se cerro automaticamente porque el ticket fue cerrado.');
        }

        return back()->with('success', 'Ticket actualizado correctamente.');
    }

    public function destroyTicket(Ticket $ticket): RedirectResponse
    {
        if (!$this->canDeleteTicket($ticket)) {
            abort(403);
        }

        $ticket->estado = 'cerrado';
        $ticket->fecha_cierre = now();
        $ticket->save();
        $this->closeActiveRemoteSessionsForTicket($ticket, 'La sesion remota se cerro automaticamente porque el ticket fue eliminado.');
        $ticket->delete();

        return back()->with('success', 'Ticket eliminado correctamente.');
    }

    public function toggleTicketCheckpoint(int $ticket): RedirectResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $ticketModel = Ticket::withTrashed()->findOrFail($ticket);

        if ($ticketModel->trashed()) {
            $ticketModel->restore();
            return back()->with('success', 'Ticket habilitado correctamente.');
        }

        $ticketModel->estado = 'cerrado';
        $ticketModel->fecha_cierre = now();
        $ticketModel->save();
        $this->closeActiveRemoteSessionsForTicket($ticketModel, 'La sesion remota se cerro automaticamente porque el ticket fue deshabilitado.');
        $ticketModel->delete();

        return back()->with('success', 'Ticket deshabilitado correctamente.');
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
        $this->closeActiveRemoteSessionsForTicket($ticket, 'La sesion remota se cerro automaticamente porque el ticket fue finalizado.');

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => 'Ticket finalizado por ' . auth()->user()->name . '.',
            'tipo' => 'atencion',
        ]);

        return back()->with('success', 'Ticket finalizado correctamente.');
    }

    private function ticketAttachmentDisk(): string
    {
        $configuredDisk = (string) config('helpdesk.chat_attachments.disk', 'public');
        $availableDisks = array_keys((array) config('filesystems.disks', []));

        return in_array($configuredDisk, $availableDisks, true) ? $configuredDisk : 'public';
    }

    private function ticketAttachmentDirectory(Ticket $ticket): string
    {
        $baseDirectory = trim((string) config('helpdesk.chat_attachments.directory', 'ticket-mensajes'), '/');

        return $baseDirectory . '/ticket-' . $ticket->id . '/' . now()->format('Y/m');
    }

    private function ticketsQueryForCurrentUser(bool $includeDeleted = false)
    {
        $query = Ticket::query();

        if ($includeDeleted || (auth()->check() && auth()->user()->hasRole('Administrador'))) {
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

    private function hasActiveRemoteSessionForEmployee(int $employeeId, ?int $exceptSessionId = null): bool
    {
        if ($employeeId <= 0) {
            return false;
        }

        return TicketRemoteSession::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_remote_sessions.ticket_id')
            ->where('tickets.empleado_id', $employeeId)
            ->whereIn('ticket_remote_sessions.status', ['pending', 'accepted'])
            ->where('tickets.estado', 'en_proceso')
            ->when($exceptSessionId, fn ($query) => $query->where('ticket_remote_sessions.id', '!=', $exceptSessionId))
            ->exists();
    }

    private function hasActiveRemoteSessionForClient(int $clientId, ?int $exceptSessionId = null): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        return TicketRemoteSession::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_remote_sessions.ticket_id')
            ->where('tickets.cliente_id', $clientId)
            ->whereIn('ticket_remote_sessions.status', ['pending', 'accepted'])
            ->where('tickets.estado', 'en_proceso')
            ->when($exceptSessionId, fn ($query) => $query->where('ticket_remote_sessions.id', '!=', $exceptSessionId))
            ->exists();
    }

    private function closeActiveRemoteSessionsForTicket(Ticket $ticket, ?string $note = null): void
    {
        if (!Schema::hasTable('ticket_remote_sessions')) {
            return;
        }

        $activeSessions = $ticket->remoteSessions()
            ->whereIn('status', ['pending', 'accepted'])
            ->get();

        if ($activeSessions->isEmpty()) {
            return;
        }

        $now = now();
        $userId = auth()->id();
        $defaultNote = 'La sesion remota se cerro automaticamente porque el ticket cambio de estado.';

        foreach ($activeSessions as $session) {
            $session->update([
                'status' => 'ended',
                'ended_at' => $now,
                'cancelled_by_user_id' => $userId,
                'note' => $note ?? $defaultNote,
            ]);
        }
    }

    private function tryCloseAnyDeskSession(): bool
    {
        $customCommand = trim((string) config('services.anydesk.close_command', ''));
        if ($customCommand !== '') {
            $process = Process::fromShellCommandline($customCommand);
            $process->setTimeout(8);
            $process->run();

            return $process->isSuccessful();
        }

        // En Windows: localizar procesos AnyDesk, intentar cerrarlos y confirmar
        // que no queden vivos. Evita reportar "exito" falso.
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $script = <<<'POWERSHELL'
$found = @(
    Get-Process -ErrorAction SilentlyContinue |
    Where-Object {
        $_.ProcessName -match '^(?i)anydesk' -or $_.ProcessName -match '^(?i)ad_svc$'
    }
)
$foundCount = $found.Count
$stoppedCount = 0

foreach ($proc in $found) {
    try {
        Stop-Process -Id $proc.Id -Force -ErrorAction Stop
        $stoppedCount++
    } catch {
    }
}

Start-Sleep -Milliseconds 250
$remainingCount = @(
    Get-Process -ErrorAction SilentlyContinue |
    Where-Object {
        $_.ProcessName -match '^(?i)anydesk' -or $_.ProcessName -match '^(?i)ad_svc$'
    }
).Count

if ($remainingCount -gt 0) {
    try {
        & taskkill /F /T /FI "IMAGENAME eq AnyDesk*" | Out-Null
    } catch {
    }
    Start-Sleep -Milliseconds 250
    $remainingCount = @(
        Get-Process -ErrorAction SilentlyContinue |
        Where-Object {
            $_.ProcessName -match '^(?i)anydesk' -or $_.ProcessName -match '^(?i)ad_svc$'
        }
    ).Count
}

Write-Output "$foundCount|$stoppedCount|$remainingCount"
POWERSHELL;

                $process = new Process(['powershell', '-NoProfile', '-Command', $script]);
                $process->setTimeout(10);
                $process->run();

                if (!$process->isSuccessful()) {
                    return false;
                }

                $result = trim((string) $process->getOutput());
                $parts = explode('|', $result);
                if (count($parts) !== 3) {
                    return false;
                }

                $foundCount = (int) $parts[0];
                $stoppedCount = (int) $parts[1];
                $remainingCount = (int) $parts[2];

                if ($foundCount === 0) {
                    return true;
                }

                return $stoppedCount > 0 && $remainingCount === 0;
            } catch (\Throwable $exception) {
                return false;
            }
        }

        try {
            $kill = new Process(['pkill', '-f', 'anydesk']);
            $kill->setTimeout(8);
            $kill->run();

            if ($kill->isSuccessful()) {
                return true;
            }

            $output = strtolower($kill->getErrorOutput() . ' ' . $kill->getOutput());
            if ($this->isProcessNotFoundMessage($output)) {
                return true;
            }
        } catch (\Throwable $exception) {
            return false;
        }

        return $kill->isSuccessful();
    }

    private function isProcessNotFoundMessage(string $output): bool
    {
        return str_contains($output, 'not found')
            || str_contains($output, 'no process found')
            || str_contains($output, 'no such process')
            || str_contains($output, 'no se encuentra')
            || str_contains($output, 'no se encontraron')
            || str_contains($output, 'no se encuentra ningun proceso');
    }

    private function resolveAnyDeskSupportCode(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = $this->resolveAnyDeskExecutablePathWindows();
            if ($path === null) {
                return null;
            }

            try {
                $process = new Process([$path, '--get-id']);
                $process->setTimeout(8);
                $process->run();

                if (!$process->isSuccessful()) {
                    return null;
                }

                return $this->extractAnyDeskCode($process->getOutput() . "\n" . $process->getErrorOutput());
            } catch (\Throwable $exception) {
                return null;
            }
        }

        try {
            $process = new Process(['anydesk', '--get-id']);
            $process->setTimeout(8);
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            return $this->extractAnyDeskCode($process->getOutput() . "\n" . $process->getErrorOutput());
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resolveAnyDeskExecutablePathWindows(): ?string
    {
        $configured = trim((string) env('ANYDESK_EXECUTABLE', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            'C:\\Program Files (x86)\\AnyDesk\\AnyDesk.exe',
            'C:\\Program Files\\AnyDesk\\AnyDesk.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $globPatterns = [
            'C:\\Program Files\\AnyDesk*\\AnyDesk*.exe',
            'C:\\Program Files (x86)\\AnyDesk*\\AnyDesk*.exe',
        ];

        foreach ($globPatterns as $pattern) {
            $matches = glob($pattern) ?: [];
            foreach ($matches as $match) {
                if (is_file($match)) {
                    return $match;
                }
            }
        }

        return null;
    }

    private function extractAnyDeskCode(string $rawOutput): ?string
    {
        $output = trim($rawOutput);
        if ($output === '') {
            return null;
        }

        if (preg_match('/\d(?:[\s.-]?\d){7,}/', $output, $match) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $match[0] ?? '');
        if (!is_string($digits) || strlen($digits) < 8) {
            return null;
        }

        return trim(chunk_split($digits, 3, ' '));
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', 10);

        return in_array($perPage, [10, 15], true) ? $perPage : 10;
    }

    private function resolveReviewRange(Request $request): array
    {
        return app(ReviewRangeService::class)->resolveFromRequest($request);
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
