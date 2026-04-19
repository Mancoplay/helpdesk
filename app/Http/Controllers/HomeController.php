<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreClienteRequest;
use App\Http\Requests\Admin\StoreEmpleadoRequest;
use App\Http\Requests\Admin\UpdateClienteRequest;
use App\Http\Requests\Admin\UpdateEmpleadoRequest;
use App\Services\TicketNotificationService;
use App\Services\ReviewRangeService;
use App\Models\Cliente;
use App\Models\AreaTrabajo;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\SystemSetting;
use App\Models\Ticket;
use App\Models\TicketMensaje;
use App\Models\TicketRemoteSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $this->ensureBoliviaDepartments();
        $this->ensureDefaultWorkAreas();

        $query = User::with(['roles', 'departamento', 'areaTrabajo'])->latest();
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $roleFilter = trim((string) $request->get('rol', ''));
        $perPage = $this->resolvePerPage($request);
        $this->applyUsersDirectoryFilters($query, $search, $roleFilter);

        $usuarios = $query->paginate($perPage)->withQueryString();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'departamentosBolivia' => $this->orderedBoliviaDepartments(),
            'areasTrabajoActivas' => $this->orderedFunctionalWorkAreas(),
            'rolesDisponibles' => ['Administrador', 'Usuario', 'Empleado'],
            'selectedRole' => $roleFilter,
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function reportesUsuarios(Request $request)
    {
        $this->ensureBoliviaDepartments();
        $this->ensureDefaultWorkAreas();

        $validated = $request->validate([
            'periodo' => ['nullable', Rule::in(['mes_actual', 'mes_anterior', 'personalizado'])],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $search = trim((string) $request->get('q', $request->get('search', '')));
        $roleFilter = trim((string) $request->get('rol', ''));
        $departamentoId = (int) $request->get('departamento_id', 0);
        $areaTrabajoId = (int) $request->get('area_trabajo_id', 0);
        $periodo = $validated['periodo'] ?? 'mes_actual';

        $fechaDesde = '';
        $fechaHasta = '';

        if ($periodo === 'mes_actual') {
            $fechaDesde = now()->startOfMonth()->toDateString();
            $fechaHasta = now()->endOfMonth()->toDateString();
        } elseif ($periodo === 'mes_anterior') {
            $lastMonth = now()->subMonthNoOverflow();
            $fechaDesde = $lastMonth->copy()->startOfMonth()->toDateString();
            $fechaHasta = $lastMonth->copy()->endOfMonth()->toDateString();
        } else {
            $fechaDesde = $validated['fecha_desde'] ?? '';
            $fechaHasta = $validated['fecha_hasta'] ?? '';
        }

        $query = User::with(['roles', 'departamento', 'areaTrabajo'])
            ->where('activo', true)
            ->select('users.*')
            ->selectSub(function ($subQuery) use ($fechaDesde, $fechaHasta): void {
                $subQuery->from('tickets')
                    ->selectRaw('COUNT(DISTINCT tickets.id)')
                    ->where(function ($ticketFilter) use ($fechaDesde, $fechaHasta): void {
                        $ticketFilter->whereColumn('tickets.cliente_id', 'users.id')
                            ->orWhereColumn('tickets.empleado_id', 'users.id');
                    });

                if ($fechaDesde !== '') {
                    $subQuery->whereDate('tickets.created_at', '>=', $fechaDesde);
                }
                if ($fechaHasta !== '') {
                    $subQuery->whereDate('tickets.created_at', '<=', $fechaHasta);
                }
            }, 'tickets_total_count')
            ->latest();
        $this->applyUsersDirectoryFilters($query, $search, $roleFilter, $departamentoId, $areaTrabajoId, '', $fechaDesde, $fechaHasta);

        $usuarios = $query->get();
        $summaryBase = User::query()->where('activo', true);
        $this->applyUsersDirectoryFilters($summaryBase, $search, $roleFilter, $departamentoId, $areaTrabajoId, '', $fechaDesde, $fechaHasta);

        return view('reportes.usuarios', [
            'usuarios' => $usuarios,
            'searchQuery' => $search,
            'selectedRole' => $roleFilter,
            'selectedPeriodo' => $periodo,
            'selectedDepartamentoId' => $departamentoId > 0 ? $departamentoId : '',
            'selectedAreaTrabajoId' => $areaTrabajoId > 0 ? $areaTrabajoId : '',
            'selectedFechaDesde' => $fechaDesde,
            'selectedFechaHasta' => $fechaHasta,
            'rolesDisponibles' => ['Administrador', 'Usuario', 'Empleado'],
            'departamentosBolivia' => $this->orderedBoliviaDepartments(),
            'areasTrabajoActivas' => $this->orderedFunctionalWorkAreas(),
            'summary' => [
                'total' => (clone $summaryBase)->count(),
                'tickets_total' => (int) $usuarios->sum('tickets_total_count'),
            ],
            'generatedAt' => now(),
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
            'nombres' => $validated['nombres'],
            'apellidos' => $validated['apellidos'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'empresa' => $validated['empresa'] ?? null,
            'activo' => true,
            'departamento_id' => (int) $validated['departamento_id'],
            'area_trabajo_id' => (int) $validated['area_trabajo_id'],
        ]);
        $usuario->syncRoles([$validated['rol']]);
        $this->syncEmployeeDepartmentMapping($usuario, $validated['rol'], (int) $validated['departamento_id']);

        return back()->with('success', 'Usuario agregado correctamente.');
    }

    public function updateCliente(UpdateClienteRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $user->name = trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? ''));
        $user->nombres = $validated['nombres'];
        $user->apellidos = $validated['apellidos'];
        $user->email = $validated['email'];
        $user->telefono = $validated['telefono'] ?? null;
        $user->direccion = $validated['direccion'] ?? null;
        $user->empresa = $validated['empresa'] ?? null;
        $user->departamento_id = (int) $validated['departamento_id'];
        $user->area_trabajo_id = (int) $validated['area_trabajo_id'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['rol']]);
        $this->syncEmployeeDepartmentMapping($user, $validated['rol'], (int) $validated['departamento_id']);

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

    public function toggleClienteCheckpoint(User $user): RedirectResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $user->activo = !$user->activo;
        $user->save();

        return back()->with('success', $user->activo
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
            'nombres' => $validated['nombres'],
            'apellidos' => $validated['apellidos'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
            'activo' => true,
            'departamento_id' => $selectedDepartmentIds->first(),
        ]);
        $user->syncRoles(['Empleado']);
        Empleado::find($user->id)?->departamentos()->sync($selectedDepartmentIds->all());

        return back()->with('success', 'Empleado agregado correctamente.');
    }

    public function updateEmpleado(UpdateEmpleadoRequest $request, Empleado $empleado): RedirectResponse
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

        try {
            DB::transaction(function () use ($empleado, $validated, $selectedDepartmentIds): void {
                $empleado->name = trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? ''));
                $empleado->nombres = $validated['nombres'];
                $empleado->apellidos = $validated['apellidos'];
                $empleado->email = $validated['email'];
                $empleado->telefono = $validated['telefono'] ?? null;
                $empleado->direccion = $validated['direccion'] ?? null;
                $empleado->cargo = $validated['cargo'] ?? null;
                $empleado->departamento_id = $selectedDepartmentIds->first();

                if (!empty($validated['password'])) {
                    $empleado->password = Hash::make($validated['password']);
                }

                $empleado->save();
                $empleado->syncRoles(['Empleado']);
                $empleado->departamentos()->sync($selectedDepartmentIds->all());
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
            $empleado->delete();
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
        $this->ensureDefaultWorkAreas();
        $query = AreaTrabajo::latest();
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $perPage = $this->resolvePerPage($request);

        if ($search !== '') {
            $query->where('nombre', 'like', '%' . $search . '%')
                ->orWhere('descripcion', 'like', '%' . $search . '%');
        }

        $areasTrabajo = $query->paginate($perPage)->withQueryString();

        return view('departamentos.index', [
            'departamentos' => $areasTrabajo,
            'notificationEmail' => $this->notificationRecipientEmail(),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function updateNotificationEmail(Request $request): RedirectResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $validated = $request->validate([
            'notification_email' => ['required', 'email:rfc', 'max:255'],
        ]);

        if (!Schema::hasTable('system_settings')) {
            return back()->with('error', 'Falta ejecutar migraciones para guardar la configuracion.');
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'pending_ticket_notification_email'],
            ['value' => mb_strtolower(trim((string) $validated['notification_email']))],
        );

        return back()->with('success', 'Correo de notificaciones actualizado correctamente.');
    }

    public function storeDepartamento(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120', 'unique:areas_trabajo,nombre'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        AreaTrabajo::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activo' => (bool) ($validated['activo'] ?? true),
        ]);

        return back()->with('success', 'Area de trabajo agregada correctamente.');
    }

    public function updateDepartamento(Request $request, AreaTrabajo $departamento): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120', Rule::unique('areas_trabajo', 'nombre')->ignore($departamento->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $departamento->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activo' => (bool) ($validated['activo'] ?? false),
        ]);

        return back()->with('success', 'Area de trabajo actualizada correctamente.');
    }

    public function destroyDepartamento(AreaTrabajo $departamento): RedirectResponse
    {
        try {
            $departamento->delete();
        } catch (QueryException $exception) {
            return back()->with('error', 'No se puede eliminar el area de trabajo porque tiene registros relacionados.');
        }

        return back()->with('success', 'Area de trabajo eliminada correctamente.');
    }

    public function toggleDepartamentoCheckpoint(AreaTrabajo $departamento): RedirectResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $departamento->activo = !$departamento->activo;
        $departamento->save();

        return back()->with('success', $departamento->activo
            ? 'Area de trabajo habilitada correctamente.'
            : 'Area de trabajo deshabilitada correctamente.');
    }

    private function notificationRecipientEmail(): ?string
    {
        if (!Schema::hasTable('system_settings')) {
            return null;
        }

        $email = SystemSetting::query()
            ->where('key', 'pending_ticket_notification_email')
            ->value('value');

        if (!is_string($email)) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
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
        $prioritizeRemoteActive = Schema::hasTable('ticket_eventos');
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
                    ->where('status', 'accepted')
                    ->whereNull('ended_at')
                    ->whereHas('ticket', function ($ticketQuery) use ($ticketIdsSubquery): void {
                        $ticketQuery->where('estado', 'en_proceso')
                            ->whereIn('tickets.id', $ticketIdsSubquery);
                    })
                    ->distinct()
                    ->pluck('ticket_id');

                $pendingRemoteTicketIds = TicketRemoteSession::query()
                    ->where('status', 'pending')
                    ->whereHas('ticket', function ($ticketQuery) use ($ticketIdsSubquery): void {
                        $ticketQuery->where('estado', 'en_proceso')
                            ->whereIn('tickets.id', $ticketIdsSubquery);
                    })
                    ->distinct()
                    ->pluck('ticket_id');
            } else {
                $activeRemoteTicketId = TicketRemoteSession::query()
                    ->where('status', 'accepted')
                    ->whereNull('ended_at')
                    ->whereHas('ticket', function ($ticketQuery) use ($query): void {
                        $ticketQuery->where('estado', 'en_proceso')
                            ->whereIn('tickets.id', (clone $query)->select('tickets.id'));
                    })
                    ->latest('id')
                    ->value('ticket_id');

                $pendingRemoteTicketId = TicketRemoteSession::query()
                    ->where('status', 'pending')
                    ->whereHas('ticket', function ($ticketQuery) use ($query): void {
                        $ticketQuery->where('estado', 'en_proceso')
                            ->whereIn('tickets.id', (clone $query)->select('tickets.id'));
                    })
                    ->latest('id')
                    ->value('ticket_id');

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
            $currentEmployee = Empleado::whereKey(auth()->id())
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
        $remoteEnabled = Schema::hasTable('ticket_eventos');
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

        $remoteEnabled = Schema::hasTable('ticket_eventos');
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

        if (!Schema::hasTable('ticket_eventos')) {
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

        if (!Schema::hasTable('ticket_eventos')) {
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

        if (!Schema::hasTable('ticket_eventos')) {
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
        $cliente = Cliente::whereKey($currentUser->id)->first();

        if (!$cliente) {
            $existingUser = User::find($currentUser->id);
            if ($existingUser) {
                if (!$existingUser->hasAnyRole(['Usuario', 'Cliente'])) {
                    $existingUser->assignRole('Usuario');
                }
                if (empty($existingUser->nombres)) {
                    $existingUser->nombres = $existingUser->name;
                }
                if ($existingUser->activo === null) {
                    $existingUser->activo = true;
                }
                $existingUser->save();

                $cliente = Cliente::whereKey($existingUser->id)->first();
            }
        }

        if (!$cliente) {
            return back()->with('error', 'No se pudo identificar al usuario para crear el ticket.');
        }

        $validated['cliente_id'] = $cliente->id;
        $validated['empleado_id'] = null;

        if ($currentUser->hasRole('Empleado')) {
            $empleado = Empleado::whereKey($currentUser->id)
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
            $adminEmployee = Empleado::whereKey($currentUser->id)
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

        $employee = Empleado::with('departamentos')->whereKey($currentUser->id)
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

        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'webp', 'pdf',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'zip', 'rar', '7z', 'csv',
        ];
        $blockedMimePrefixes = [
            'text/x-php',
            'application/x-httpd-php',
            'application/x-php',
            'application/x-sh',
            'application/x-msdownload',
        ];

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
            $extension = mb_strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($extension, $allowedExtensions, true)) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'El tipo de archivo no esta permitido.'], 422);
                }

                return back()->with('error', 'Uno de los archivos tiene una extension no permitida.');
            }

            $detectedMime = (string) ($file->getMimeType() ?? '');
            if (in_array($detectedMime, $blockedMimePrefixes, true)) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Se bloqueo un archivo potencialmente peligroso.'], 422);
                }

                return back()->with('error', 'Se detecto un archivo potencialmente peligroso.');
            }

            $path = $file->store($this->ticketAttachmentDirectory($ticket), $this->ticketAttachmentDisk());
            $safeOriginalName = Str::of((string) $file->getClientOriginalName())
                ->replaceMatches('/[^\pL\pN\.\-\_\s]/u', '')
                ->limit(180, '')
                ->trim()
                ->toString();
            if ($safeOriginalName === '') {
                $safeOriginalName = 'adjunto.' . $extension;
            }

            $createdMessage = $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => $index === 0 ? ($validated['mensaje'] ?? '') : '',
                'tipo' => 'comentario',
                'imagen_path' => $path,
                'imagen_nombre' => $safeOriginalName,
                'imagen_mime' => $detectedMime !== '' ? $detectedMime : $file->getClientMimeType(),
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
            'cliente_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('activo', true))],
            'empleado_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('activo', true))],
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
                $q->whereKey(auth()->id())
                    ->orWhere('email', auth()->user()->email);
            });
        }

        if (auth()->check() && auth()->user()->hasRole('Empleado')) {
            $employee = Empleado::with('departamentos')->whereKey(auth()->id())
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
            $employee = Empleado::whereKey(auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->first();

            if (!$employee) {
                return false;
            }

            return (int) $ticket->empleado_id === (int) $employee->id;
        }

        if (auth()->user()->hasRole('Usuario')) {
            if (!$ticket->cliente) {
                return false;
            }

            return (int) ($ticket->cliente->id ?? 0) === (int) auth()->id()
                || $ticket->cliente->email === auth()->user()->email;
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

        $employee = Empleado::whereKey(auth()->id())
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

        $employee = Empleado::whereKey(auth()->id())
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

        if (!$ticket->cliente) {
            return false;
        }

        return (int) ($ticket->cliente->id ?? 0) === (int) auth()->id()
            || ($ticket->cliente->email ?? null) === auth()->user()->email;
    }

    private function hasActiveRemoteSessionForEmployee(int $employeeId, ?int $exceptSessionId = null): bool
    {
        if ($employeeId <= 0) {
            return false;
        }

        return TicketRemoteSession::query()
            ->whereIn('status', ['pending', 'accepted'])
            ->whereHas('ticket', function ($ticketQuery) use ($employeeId): void {
                $ticketQuery->where('empleado_id', $employeeId)
                    ->where('estado', 'en_proceso');
            })
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->exists();
    }

    private function hasActiveRemoteSessionForClient(int $clientId, ?int $exceptSessionId = null): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        return TicketRemoteSession::query()
            ->whereIn('status', ['pending', 'accepted'])
            ->whereHas('ticket', function ($ticketQuery) use ($clientId): void {
                $ticketQuery->where('cliente_id', $clientId)
                    ->where('estado', 'en_proceso');
            })
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->exists();
    }

    private function closeActiveRemoteSessionsForTicket(Ticket $ticket, ?string $note = null): void
    {
        if (!Schema::hasTable('ticket_eventos')) {
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

    private function applyUsersDirectoryFilters(
        Builder $query,
        string $search = '',
        string $roleFilter = '',
        int $departamentoId = 0,
        int $areaTrabajoId = 0,
        string $estadoFilter = '',
        string $fechaDesde = '',
        string $fechaHasta = ''
    ): void {
        if ($search !== '') {
            $terms = preg_split('/\s+/', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $query->where(function ($groupedQuery) use ($terms): void {
                foreach ($terms as $term) {
                    $like = '%' . $term . '%';
                    $groupedQuery->where(function ($searchQuery) use ($like): void {
                        $searchQuery->whereRaw('LOWER(nombres) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(apellidos) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(telefono) LIKE ?', [$like]);
                    });
                }
            });
        }

        if ($roleFilter !== '') {
            $query->whereHas('roles', function ($rolesQuery) use ($roleFilter): void {
                $rolesQuery->where('name', $roleFilter);
            });
        }

        if ($departamentoId > 0) {
            $query->where('departamento_id', $departamentoId);
        }

        if ($areaTrabajoId > 0) {
            $query->where('area_trabajo_id', $areaTrabajoId);
        }

        if ($estadoFilter === 'activos') {
            $query->where('activo', true);
        } elseif ($estadoFilter === 'inactivos') {
            $query->where('activo', false);
        }

        if ($fechaDesde !== '') {
            $query->whereDate('created_at', '>=', $fechaDesde);
        }

        if ($fechaHasta !== '') {
            $query->whereDate('created_at', '<=', $fechaHasta);
        }
    }

    private function orderedBoliviaDepartments()
    {
        $departmentNames = $this->boliviaDepartmentNames();
        $departments = Departamento::query()
            ->whereIn('nombre', $departmentNames)
            ->where('activo', true)
            ->get();

        return $departments
            ->sortBy(fn (Departamento $departamento): int => (int) array_search($departamento->nombre, $departmentNames, true))
            ->values();
    }

    private function ensureBoliviaDepartments(): void
    {
        foreach ($this->boliviaDepartmentNames() as $name) {
            $workArea = Departamento::query()->firstOrCreate(
                ['nombre' => $name],
                ['descripcion' => 'Departamento de Bolivia', 'activo' => true],
            );

            if (!$workArea->activo) {
                $workArea->activo = true;
                $workArea->save();
            }
        }
    }

    private function boliviaDepartmentNames(): array
    {
        return [
            'La Paz',
            'Cochabamba',
            'Santa Cruz',
            'Oruro',
            'Potosi',
            'Chuquisaca',
            'Tarija',
            'Beni',
            'Pando',
        ];
    }

    private function orderedFunctionalWorkAreas()
    {
        return AreaTrabajo::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    private function ensureDefaultWorkAreas(): void
    {
        foreach ($this->defaultWorkAreaNames() as $name) {
            $area = AreaTrabajo::query()->firstOrCreate(
                ['nombre' => $name],
                ['descripcion' => 'Area de trabajo', 'activo' => true],
            );

            if (!$area->activo) {
                $area->activo = true;
                $area->save();
            }
        }
    }

    private function defaultWorkAreaNames(): array
    {
        return [
            'Area Legal',
            'Contabilidad',
            'Reclamos',
            'RRHH',
            'Soporte Tecnico',
            'Sistemas',
            'Redes',
            'Atencion al Cliente',
        ];
    }

    private function syncEmployeeDepartmentMapping(User $user, string $role, int $departmentId): void
    {
        if ($role === 'Empleado') {
            $user->departamentos()->sync([$departmentId]);
            return;
        }

        $user->departamentos()->detach();
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

        $employee = Empleado::whereKey(auth()->id())
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
