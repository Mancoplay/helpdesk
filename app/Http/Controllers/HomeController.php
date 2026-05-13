<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreClienteRequest;
use App\Http\Requests\Admin\StoreEmpleadoRequest;
use App\Http\Requests\Admin\UpdateClienteRequest;
use App\Http\Requests\Admin\UpdateEmpleadoRequest;
use App\Services\ReviewRangeService;
use App\Services\TicketNotificationService;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;

class HomeController extends Controller
{
    private ?User $authenticatedUser = null;
    private bool $authenticatedUserResolved = false;
    private ?Empleado $authenticatedEmployee = null;
    private bool $authenticatedEmployeeResolved = false;

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
            'total_areas_trabajo' => AreaTrabajo::count(),
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

        $viewData = [
            'usuarios' => $usuarios,
            'departamentosBolivia' => $this->orderedBoliviaDepartments(),
            'areasTrabajoActivas' => $this->orderedFunctionalWorkAreas(),
            'rolesDisponibles' => ['Administrador', 'Usuario', 'Empleado'],
            'selectedRole' => $roleFilter,
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ];

        if ($request->ajax()) {
            return view('usuarios.partials.table', $viewData);
        }

        return view('usuarios.index', $viewData);
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
        $this->applyUsersDirectoryFilters($query, $search, $roleFilter, $departamentoId, $areaTrabajoId);

        $usuarios = $query->get();
        $summaryBase = User::query()->where('activo', true);
        $this->applyUsersDirectoryFilters($summaryBase, $search, $roleFilter, $departamentoId, $areaTrabajoId);

        $detalleTicketsUsuario = collect();
        $usuarioDetalle = null;
        $detalleRelacionLabel = 'Participacion';
        $isSingleUserReport = $usuarios->count() === 1;

        if ($usuarios->count() === 1) {
            $usuarioDetalle = $usuarios->first();
            $detalleRole = $roleFilter !== ''
                ? $roleFilter
                : (string) ($usuarioDetalle->getRoleNames()->first() ?? '');

            $detalleRelacionLabel = match ($detalleRole) {
                'Empleado' => 'Usuario',
                'Usuario', 'Cliente', 'Administrador' => 'Soporte',
                default => 'Participacion',
            };

            $detalleTicketsUsuario = Ticket::withTrashed()
                ->with(['cliente', 'empleado', 'departamento'])
                ->where(function ($ticketQuery) use ($usuarioDetalle): void {
                    $ticketQuery->where('cliente_id', $usuarioDetalle->id)
                        ->orWhere('empleado_id', $usuarioDetalle->id);
                })
                ->when($fechaDesde !== '', fn ($ticketQuery) => $ticketQuery->whereDate('created_at', '>=', $fechaDesde))
                ->when($fechaHasta !== '', fn ($ticketQuery) => $ticketQuery->whereDate('created_at', '<=', $fechaHasta))
                ->orderByDesc('created_at')
                ->get()
                ->map(function (Ticket $ticket) use ($detalleRole) {
                    $clienteNombre = trim((string) ($ticket->cliente->nombre_completo ?? ''));
                    $empleadoNombre = trim((string) ($ticket->empleado->nombre_completo ?? ''));

                    $detalleRelacionValor = match ($detalleRole) {
                        'Empleado' => $clienteNombre !== '' ? $clienteNombre : 'Sin usuario asignado',
                        'Usuario', 'Cliente', 'Administrador' => $empleadoNombre !== '' ? $empleadoNombre : 'Sin soporte asignado',
                        default => '-',
                    };

                    return [
                        'codigo' => $ticket->codigo,
                        'asunto' => $ticket->asunto,
                        'departamento' => $ticket->departamento->nombre ?? '-',
                        'estado' => $ticket->estado,
                        'relacion' => $detalleRelacionValor,
                        'puntuacion' => $ticket->atencion_puntuacion,
                        'fecha' => optional($ticket->created_at)->format('Y-m-d H:i'),
                    ];
                });
        }

        $printTicketsTotal = $isSingleUserReport
            ? (int) ($usuarios->first()->tickets_total_count ?? 0)
            : (int) $usuarios->sum('tickets_total_count');

        $printTicketsLabel = $isSingleUserReport
            ? 'Total de tickets del usuario'
            : 'Total de tickets';

        $viewData = [
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
            'printSummary' => [
                'is_single_user' => $isSingleUserReport,
                'tickets_total' => $printTicketsTotal,
                'tickets_label' => $printTicketsLabel,
            ],
            'usuarioDetalle' => $usuarioDetalle,
            'detalleTicketsUsuario' => $detalleTicketsUsuario,
            'detalleRelacionLabel' => $detalleRelacionLabel,
            'generatedAt' => now(),
            'menuBadges' => $this->menuBadges(),
        ];

        if ($request->ajax()) {
            return view('reportes.partials.usuarios-results', $viewData);
        }

        return view('reportes.usuarios', $viewData);
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

        $viewData = [
            'empleados' => $empleados,
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'departamentosActivos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ];

        if ($request->ajax()) {
            return view('empleados.partials.table', $viewData);
        }

        return view('empleados.index', $viewData);
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
            'puntuacion_promedio' => (float) ($empleado->puntuacion_promedio ?? 0),
            'puntuaciones_count' => (int) ($empleado->puntuaciones_count ?? 0),
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
        $query = AreaTrabajo::query()->orderBy('nombre');
        $search = trim((string) $request->get('q', $request->get('search', '')));
        $perPage = $this->resolvePerPage($request);

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('nombre', 'like', '%' . $search . '%')
                    ->orWhere('descripcion', 'like', '%' . $search . '%');
            });
        }

        $areasTrabajo = $query->paginate($perPage)->withQueryString();

        $viewData = [
            'departamentos' => $areasTrabajo,
            'notificationEmail' => $this->notificationRecipientEmail(),
            'searchQuery' => $search,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ];

        if ($request->ajax()) {
            return view('departamentos.partials.table', $viewData);
        }

        return view('departamentos.index', $viewData);
    }

    public function updateNotificationEmail(Request $request): RedirectResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $validated = $request->validate([
            'notification_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        if (!Schema::hasTable('system_settings')) {
            return back()->with('error', 'Falta ejecutar migraciones para guardar la configuracion.');
        }

        $notificationEmail = mb_strtolower(trim((string) ($validated['notification_email'] ?? '')));

        if ($notificationEmail === '') {
            SystemSetting::query()
                ->where('key', 'pending_ticket_notification_email')
                ->delete();
        } else {
            SystemSetting::query()->updateOrCreate(
                ['key' => 'pending_ticket_notification_email'],
                ['value' => $notificationEmail],
            );
        }

        Cache::forget('settings:pending_ticket_notification_email');

        return back()->with('success', $notificationEmail !== ''
            ? 'Correo de notificaciones actualizado correctamente.'
            : 'Las notificaciones por correo fueron desactivadas. Las notificaciones del sistema seguiran activas.');
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
        Cache::forget('catalog:functional_work_areas:active');
        Cache::forget('catalog:functional_work_areas:seeded');

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
        Cache::forget('catalog:functional_work_areas:active');
        Cache::forget('catalog:functional_work_areas:seeded');

        return back()->with('success', 'Area de trabajo actualizada correctamente.');
    }

    public function destroyDepartamento(AreaTrabajo $departamento): RedirectResponse
    {
        try {
            $departamento->delete();
            Cache::forget('catalog:functional_work_areas:active');
            Cache::forget('catalog:functional_work_areas:seeded');
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
        Cache::forget('catalog:functional_work_areas:active');
        Cache::forget('catalog:functional_work_areas:seeded');

        return back()->with('success', $departamento->activo
            ? 'Area de trabajo habilitada correctamente.'
            : 'Area de trabajo deshabilitada correctamente.');
    }

    private function notificationRecipientEmail(): ?string
    {
        return Cache::remember('settings:pending_ticket_notification_email', now()->addMinutes(5), function (): ?string {
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
        });
    }

    public function tickets(Request $request)
    {
        $currentUser = $this->currentUser() ?? auth()->user();
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
        $assignedEmployeeIds = $tickets->getCollection()
            ->flatMap(fn (Ticket $ticket) => $ticket->assigned_employee_ids ?? [])
            ->map(fn ($employeeId) => (int) $employeeId)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $assignedEmployeesById = empty($assignedEmployeeIds)
            ? collect()
            : Empleado::query()
                ->whereIn('id', $assignedEmployeeIds)
                ->get()
                ->keyBy('id');

        $currentEmployee = null;
        if ($currentUser->hasRole('Empleado')) {
            $currentEmployee = $this->currentEmployee();
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
            'assignedEmployeesById' => $assignedEmployeesById,
            'menuBadges' => $this->menuBadges(),
        ];

        if ($canCreateTickets) {
            $viewData['departamentosActivos'] = Departamento::where('activo', true)->orderBy('nombre')->get();
            $viewData['nextTicketCode'] = $this->nextTicketCode();
        }

        if ($request->ajax()) {
            return view('tickets.partials.table', $viewData);
        }

        return view('tickets.index', $viewData);
    }

    public function editTicket(Ticket $ticket)
    {
        if (!$this->canEditTicket($ticket)) {
            abort(403);
        }

        $ticket->loadMissing('assignmentRequestBy');

        return view('tickets.edit', [
            'ticket' => $ticket,
            'clientes' => Cliente::where('activo', true)->orderBy('nombres')->orderBy('apellidos')->get(),
            'empleados' => Empleado::where('activo', true)->orderBy('nombres')->orderBy('apellidos')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'assignedEmployeeIds' => collect($ticket->assigned_employee_ids ?? [])
                ->map(fn ($employeeId) => (int) $employeeId)
                ->filter(fn (int $employeeId) => $employeeId > 0 && $employeeId !== (int) ($ticket->empleado_id ?? 0))
                ->unique()
                ->values()
                ->all(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function showTicket(Ticket $ticket)
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        $ticket->load(['cliente', 'empleado', 'departamento', 'assignmentRequestBy']);
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
        $remoteSupportCode = $remoteSession
            ? (string) ($remoteSession->support_code ?? '')
            : '';
        $remoteRustDeskCode = $remoteSession
            ? (string) ($remoteSession->rustdesk_code ?? '')
            : '';

        if (
            $remoteSession
            && $remoteSession->status === 'accepted'
            && $remoteSupportCode === ''
        ) {
            $remoteSupportCode = $this->rememberedAnyDeskSupportCodeForClient(
                (int) ($ticket->cliente_id ?? 0),
                (int) $remoteSession->id,
            );
        }

        if (
            $remoteSession
            && $remoteSession->status === 'accepted'
            && $remoteRustDeskCode === ''
        ) {
            $remoteRustDeskCode = $this->rememberedRustDeskCodeForClient(
                (int) ($ticket->cliente_id ?? 0),
                (int) $remoteSession->id,
            );
        }

        return view('tickets.show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'remoteEnabled' => $remoteEnabled,
            'remoteSession' => $remoteSession,
            'remoteSupportCode' => $remoteSupportCode,
            'remoteRustDeskCode' => $remoteRustDeskCode,
            'assignedEmployeeNames' => $this->ticketAssignedEmployeeNames($ticket),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function requestTicketAssignmentChange(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        if (!$this->canAccessTicket($ticket) || !auth()->user()->hasRole('Empleado')) {
            abort(403);
        }

        if (!$this->isAssignedEmployeeForTicket($ticket)) {
            abort(403, 'Solo un empleado asignado puede solicitar cambios de asignacion.');
        }

        if (!in_array((string) $ticket->estado, ['pendiente', 'en_proceso'], true)) {
            return $this->ticketAssignmentRequestResponse($request, 'Solo se puede solicitar cambios en tickets pendientes o en proceso.', false);
        }

        $validated = $request->validate([
            'request_type' => ['required', Rule::in(['change_employee', 'add_employees'])],
        ], [
            'request_type.required' => 'Selecciona una opcion de solicitud.',
        ]);

        $requestType = (string) $validated['request_type'];
        $requestTypeLabel = $requestType === 'change_employee'
            ? 'Cambio de empleado'
            : 'Asignacion de empleados';

        $ticket->forceFill([
            'assignment_request_type' => $requestType,
            'assignment_request_by_id' => auth()->id(),
            'assignment_request_at' => now(),
        ])->save();

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => auth()->user()->name . ' solicito: ' . $requestTypeLabel . '.',
            'tipo' => 'atencion',
        ]);

        app(TicketNotificationService::class)->notifyTicketAssignmentRequest($ticket, $requestTypeLabel, auth()->user()->name);

        return $this->ticketAssignmentRequestResponse($request, 'Solicitud enviada a los administradores.', true);
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
        $remoteSupportCode = $remoteSession
            ? (string) ($remoteSession->support_code ?? '')
            : '';
        $remoteRustDeskCode = $remoteSession
            ? (string) ($remoteSession->rustdesk_code ?? '')
            : '';

        if (
            $remoteSession
            && $remoteSession->status === 'accepted'
            && $remoteSupportCode === ''
        ) {
            $remoteSupportCode = $this->rememberedAnyDeskSupportCodeForClient(
                (int) ($ticket->cliente_id ?? 0),
                (int) $remoteSession->id,
            );
        }

        if (
            $remoteSession
            && $remoteSession->status === 'accepted'
            && $remoteRustDeskCode === ''
        ) {
            $remoteRustDeskCode = $this->rememberedRustDeskCodeForClient(
                (int) ($ticket->cliente_id ?? 0),
                (int) $remoteSession->id,
            );
        }

        $ticket->loadMissing(['empleado']);
        $assignedEmployeeNames = $this->ticketAssignedEmployeeNames($ticket);

        return response()->json([
            'ok' => true,
            'messages' => $messagePayload,
            'latest_message_id' => (int) ($messages->max('id') ?? $sinceMessageId),
            'ticket' => [
                'id' => (int) $ticket->id,
                'estado' => (string) $ticket->estado,
                'empleado_id' => (int) ($ticket->empleado_id ?? 0),
                'empleado_nombre' => $assignedEmployeeNames->isNotEmpty()
                    ? $assignedEmployeeNames->implode(', ')
                    : 'Sin asignar',
            ],
            'remote' => [
                'enabled' => $remoteEnabled,
                'id' => (int) ($remoteSession->id ?? 0),
                'status' => (string) ($remoteSession->status ?? ''),
                'support_code' => $remoteSupportCode,
                'rustdesk_code' => $remoteRustDeskCode,
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
            return back()->with('error', 'La funcionalidad de soporte remoto aún no está disponible.');
        }

        if (!auth()->user()->hasRole('Administrador') && !$this->isAssignedEmployeeForTicket($ticket)) {
            abort(403, 'Solo el empleado asignado o un administrador puede iniciar la conexión remota.');
        }

        if ($ticket->estado !== 'en_proceso') {
            return back()->with('error', 'La conexión remota solo se puede solicitar cuando el ticket está en proceso.');
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

    public function updateRemoteSession(Request $request, Ticket $ticket, TicketRemoteSession $remoteSession): RedirectResponse|JsonResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!Schema::hasTable('ticket_eventos')) {
            return back()->with('error', 'La funcionalidad de soporte remoto aún no está disponible.');
        }

        if ((int) $remoteSession->ticket_id !== (int) $ticket->id) {
            abort(404);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject', 'share_code', 'end', 'signal_closed'])],
            'support_code' => ['nullable', 'string', 'max:40', 'regex:/^[0-9]+$/'],
            'rustdesk_code' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
        ], [
            'support_code.regex' => 'El código de AnyDesk solo debe contener números.',
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
                return back()->with('error', 'Ya tienes otra conexión remota activa. Finalízala antes de aceptar una nueva.');
            }

            $rememberedSupportCode = $this->rememberedAnyDeskSupportCodeForClient(
                (int) ($ticket->cliente_id ?? 0),
                (int) $remoteSession->id,
            );
            $rememberedRustDeskCode = $this->rememberedRustDeskCodeForClient(
                (int) ($ticket->cliente_id ?? 0),
                (int) $remoteSession->id,
            );

            $updateData = [
                'status' => 'accepted',
                'responded_at' => now(),
            ];

            if (blank($remoteSession->support_code) && $rememberedSupportCode !== '') {
                $updateData['support_code'] = $rememberedSupportCode;
            }
            if (blank($remoteSession->rustdesk_code) && $rememberedRustDeskCode !== '') {
                $updateData['rustdesk_code'] = $rememberedRustDeskCode;
            }

            $remoteSession->update($updateData);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Solicitud remota aceptada correctamente.',
                    'remote' => [
                        'id' => (int) $remoteSession->id,
                        'status' => (string) $remoteSession->status,
                        'support_code' => (string) ($remoteSession->support_code ?? ''),
                        'rustdesk_code' => (string) ($remoteSession->rustdesk_code ?? ''),
                    ],
                ]);
            }

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

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Solicitud remota rechazada.',
                    'remote' => [
                        'id' => (int) $remoteSession->id,
                        'status' => (string) $remoteSession->status,
                        'support_code' => (string) ($remoteSession->support_code ?? ''),
                        'rustdesk_code' => (string) ($remoteSession->rustdesk_code ?? ''),
                    ],
                ]);
            }

            return back()->with('success', 'Solicitud remota rechazada.');
        }

        if ($action === 'share_code') {
            if (
                !auth()->user()->hasRole('Administrador')
                && !$this->isTicketClientOwner($ticket)
                && !$this->isAssignedEmployeeForTicket($ticket)
            ) {
                abort(403);
            }
            if ($remoteSession->status !== 'accepted') {
                return back()->with('error', 'Debes aceptar la solicitud antes de compartir el código.');
            }

            $supportCode = preg_replace('/\D+/', '', trim((string) ($validated['support_code'] ?? ''))) ?? '';
            $rustDeskCode = preg_replace('/[^A-Za-z0-9_-]+/', '', trim((string) ($validated['rustdesk_code'] ?? ''))) ?? '';
            if ($supportCode === '' && $rustDeskCode === '') {
                return back()->with('error', 'Debes ingresar el codigo de AnyDesk o RustDesk.');
            }

            $updateData = [];
            if ($supportCode !== '') {
                $updateData['support_code'] = $supportCode;
            }
            if ($rustDeskCode !== '') {
                $updateData['rustdesk_code'] = $rustDeskCode;
            }

            $remoteSession->update($updateData);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Código de AnyDesk compartido.',
                    'remote' => [
                        'id' => (int) $remoteSession->id,
                        'status' => (string) $remoteSession->status,
                        'support_code' => (string) ($remoteSession->support_code ?? ''),
                        'rustdesk_code' => (string) ($remoteSession->rustdesk_code ?? ''),
                    ],
                ]);
            }

            return back()->with('success', 'Código de AnyDesk compartido.');
        }

        if ($action === 'end') {
            if (!auth()->user()->hasRole('Administrador') && !$this->isAssignedEmployeeForTicket($ticket)) {
                abort(403);
            }
            if (!in_array($remoteSession->status, ['accepted', 'pending'], true)) {
                return back()->with('error', 'No hay una sesión remota activa para finalizar.');
            }

            $remoteSession->update([
                'status' => 'ended',
                'ended_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
            ]);

            $anyDeskClosed = $this->tryCloseAnyDeskSession();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => $anyDeskClosed
                        ? 'Conexión remota finalizada correctamente y AnyDesk se cerró.'
                        : 'Conexión remota finalizada en el sistema.',
                    'remote' => [
                        'id' => (int) $remoteSession->id,
                        'status' => (string) $remoteSession->status,
                        'support_code' => (string) ($remoteSession->support_code ?? ''),
                        'rustdesk_code' => (string) ($remoteSession->rustdesk_code ?? ''),
                    ],
                ]);
            }

            if ($anyDeskClosed) {
                return back()->with('success', 'Conexión remota finalizada correctamente y AnyDesk se cerró.');
            }

            return back()->with('error', 'Conexión remota finalizada en el sistema.');
        }

        if (
            !auth()->user()->hasRole('Administrador')
            && !$this->isTicketClientOwner($ticket)
            && !$this->isAssignedEmployeeForTicket($ticket)
        ) {
            abort(403);
        }

        if (!in_array($remoteSession->status, ['accepted', 'pending'], true)) {
            return back()->with('error', 'No hay una sesión remota activa para cerrar.');
        }

        $remoteSession->update([
            'status' => 'ended',
            'ended_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'note' => 'Cierre informado desde la interfaz.',
        ]);

        $anyDeskClosed = $this->tryCloseAnyDeskSession();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $anyDeskClosed
                    ? 'Se marcó la sesión remota como finalizada y AnyDesk se cerró.'
                    : 'Se finalizó la sesión en el sistema.',
                'remote' => [
                    'id' => (int) $remoteSession->id,
                    'status' => (string) $remoteSession->status,
                    'support_code' => (string) ($remoteSession->support_code ?? ''),
                    'rustdesk_code' => (string) ($remoteSession->rustdesk_code ?? ''),
                ],
            ]);
        }

        if ($anyDeskClosed) {
            return back()->with('success', 'Se marcó la sesión remota como finalizada y AnyDesk se cerró.');
        }

        return back()->with('error', 'Se finalizó la sesión en el sistema.');
    }

    public function fetchRemoteSupportCode(Ticket $ticket, TicketRemoteSession $remoteSession): JsonResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!Schema::hasTable('ticket_eventos')) {
            return response()->json(['message' => 'La funcionalidad de soporte remoto aún no está disponible.'], 422);
        }

        if ((int) $remoteSession->ticket_id !== (int) $ticket->id) {
            abort(404);
        }

        if (!auth()->user()->hasRole('Administrador') && !$this->isTicketClientOwner($ticket)) {
            abort(403);
        }

        if ($remoteSession->status !== 'accepted') {
            return response()->json(['message' => 'Debes aceptar la solicitud antes de obtener el código.'], 422);
        }

        $supportCode = $this->resolveAnyDeskSupportCode();
        if ($supportCode === null) {
            return response()->json(['message' => 'No se pudo leer automáticamente el código de AnyDesk.'], 422);
        }

        $remoteSession->update([
            'support_code' => $supportCode,
        ]);

        return response()->json([
            'support_code' => $supportCode,
        ]);
    }

    public function storeTicket(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['nullable', 'string', 'max:25'],
            'asunto' => ['required', 'string', 'min:3', 'max:180'],
            'descripcion' => ['required', 'string', 'min:3'],
        ], [
            'asunto.min' => 'Debe ingresar minimo 3 caracteres en el asunto.',
            'descripcion.min' => 'Debe ingresar minimo 3 caracteres en la descripcion.',
        ]);

        $currentUser = auth()->user();
        $departmentId = (int) ($currentUser->departamento_id ?? 0);

        if ($departmentId <= 0 && $currentUser->hasRole('Empleado')) {
            $empleadoActual = Empleado::with('departamentos')
                ->whereKey($currentUser->id)
                ->orWhere('email', $currentUser->email)
                ->first();

            if ($empleadoActual) {
                $departmentId = (int) ($empleadoActual->departamentos->first()->id ?? $empleadoActual->departamento_id ?? 0);
            }
        }

        if ($departmentId <= 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No tienes un departamento asignado para crear tickets.'], 422);
            }

            return back()->with('error', 'No tienes un departamento asignado para crear tickets.');
        }

        $activeDepartmentExists = Departamento::query()
            ->whereKey($departmentId)
            ->where('activo', true)
            ->exists();

        if (!$activeDepartmentExists) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tu departamento asignado no esta activo. Contacta al administrador.'], 422);
            }

            return back()->with('error', 'Tu departamento asignado no esta activo. Contacta al administrador.');
        }

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
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No se pudo identificar al usuario para crear el ticket.'], 422);
            }

            return back()->with('error', 'No se pudo identificar al usuario para crear el ticket.');
        }

        $validated['cliente_id'] = $cliente->id;
        $validated['departamento_id'] = $departmentId;
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
        while (Ticket::withTrashed()->where('codigo', $validated['codigo'])->exists()) {
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

        app(TicketNotificationService::class)->notifyTicketCreated($ticket);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ticket agregado correctamente.',
                'ticket_id' => (int) $ticket->id,
                'codigo' => $ticket->codigo,
                'next_code' => $this->nextTicketCode(),
            ]);
        }

        return back()->with('success', 'Ticket agregado correctamente.');
    }

    public function attendTicket(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if ($ticket->estado !== 'pendiente') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Solo se pueden atender tickets en estado pendiente.'], 422);
            }

            return back()->with('error', 'Solo se pueden atender tickets en estado pendiente.');
        }

        $currentUser = auth()->user();
        $attendedByName = $currentUser->name;

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
                'mensaje' => 'Ticket atendido por ' . $attendedByName . '.',
                'tipo' => 'atencion',
            ]);

            app(TicketNotificationService::class)->notifyTicketAttended($ticket, $attendedByName);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Ticket atendido correctamente.',
                    'ticket_id' => (int) $ticket->id,
                    'redirect_url' => route('tickets.show', $ticket),
                ]);
            }

            return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket atendido correctamente.');
        }

        $employee = Empleado::with('departamentos')->whereKey($currentUser->id)
            ->orWhere('email', $currentUser->email)
            ->first();

        if (!$employee) {
            abort(403, 'No se pudo identificar al empleado.');
        }

        $ticket->empleado_id = $employee->id;
        $ticket->estado = 'en_proceso';
        $ticket->save();

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => 'Ticket atendido por ' . $attendedByName . '.',
            'tipo' => 'atencion',
        ]);

        app(TicketNotificationService::class)->notifyTicketAttended($ticket, $attendedByName);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ticket atendido correctamente.',
                'ticket_id' => (int) $ticket->id,
                'redirect_url' => route('tickets.show', $ticket),
            ]);
        }

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

    public function rateTicket(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!$this->isTicketClientOwner($ticket)) {
            abort(403);
        }

        if ($ticket->estado !== 'finalizado') {
            return $this->ticketRatingResponse($request, 'Solo puedes calificar un ticket finalizado.', false);
        }

        if ((int) ($ticket->empleado_id ?? 0) <= 0) {
            return $this->ticketRatingResponse($request, 'Este ticket no tiene empleado asignado para calificar.', false);
        }

        if (!is_null($ticket->atencion_puntuacion)) {
            return $this->ticketRatingResponse($request, 'Este ticket ya fue calificado.', true);
        }

        $validated = $request->validate([
            'puntuacion' => ['required', 'integer', 'min:1', 'max:5'],
        ], [
            'puntuacion.required' => 'Selecciona una puntuacion del 1 al 5.',
            'puntuacion.min' => 'La puntuacion minima es 1.',
            'puntuacion.max' => 'La puntuacion maxima es 5.',
        ]);

        $ticket->forceFill([
            'atencion_puntuacion' => (int) $validated['puntuacion'],
            'puntuado_por_id' => auth()->id(),
            'puntuado_at' => now(),
        ])->save();

        $this->refreshEmployeeRatingAverage((int) $ticket->empleado_id);

        return $this->ticketRatingResponse($request, 'Gracias por calificar la atencion.', true);
    }

    public function updateTicket(Request $request, Ticket $ticket): RedirectResponse
    {
        if (!$this->canEditTicket($ticket)) {
            abort(403);
        }

        $isAdmin = auth()->user()->hasRole('Administrador');

        $rules = [
            'asunto' => ['required', 'string', 'min:3', 'max:180'],
            'descripcion' => ['required', 'string', 'min:3'],
        ];

        if ($isAdmin) {
            $rules = array_merge($rules, [
                'codigo' => ['required', 'string', 'max:25', Rule::unique('tickets', 'codigo')->ignore($ticket->id)],
                'cliente_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('activo', true))],
                'empleado_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('activo', true))],
                'assigned_employee_ids' => ['nullable', 'array'],
                'assigned_employee_ids.*' => [Rule::exists('users', 'id')->where(fn ($query) => $query->where('activo', true))],
                'departamento_id' => ['required', Rule::exists('departamentos', 'id')->where(fn ($query) => $query->where('activo', true))],
                'estado' => ['required', Rule::in(['pendiente', 'en_proceso', 'finalizado', 'cerrado'])],
            ]);
        }

        $validated = $request->validate($rules, [
            'asunto.min' => 'Debe ingresar minimo 3 caracteres en el asunto.',
            'descripcion.min' => 'Debe ingresar minimo 3 caracteres en la descripcion.',
        ]);

        if ($isAdmin && !empty($validated['empleado_id'])) {
            $empleado = Empleado::with('departamentos')->find($validated['empleado_id']);

            if (!$empleado || !$this->employeeBelongsToDepartment($empleado, (int) $validated['departamento_id'])) {
                return back()
                    ->withErrors(['empleado_id' => 'El empleado seleccionado no pertenece al departamento del ticket.'])
                    ->withInput();
            }
        }

        $previousAssigneeSignature = $this->ticketAssignedEmployeeSignature($ticket);
        $assignmentRequestType = (string) ($ticket->assignment_request_type ?? '');
        $assignmentRequestedById = (int) ($ticket->assignment_request_by_id ?? 0);

        if ($isAdmin) {
            $primaryEmployeeId = (int) ($validated['empleado_id'] ?? 0);
            $additionalEmployeeIds = collect($validated['assigned_employee_ids'] ?? [])
                ->map(fn ($employeeId) => (int) $employeeId)
                ->filter(fn (int $employeeId) => $employeeId > 0 && $employeeId !== $primaryEmployeeId)
                ->reject(fn (int $employeeId) => $assignmentRequestType === 'change_employee' && $employeeId === $assignmentRequestedById)
                ->unique()
                ->values()
                ->all();

            foreach ($additionalEmployeeIds as $employeeId) {
                $empleado = Empleado::with('departamentos')->find($employeeId);

                if (!$empleado || !$this->employeeBelongsToDepartment($empleado, (int) $validated['departamento_id'])) {
                    return back()
                        ->withErrors(['assigned_employee_ids' => 'Todos los empleados adicionales deben pertenecer al departamento del ticket.'])
                        ->withInput();
                }
            }

            $validated['assigned_employee_ids'] = $additionalEmployeeIds;
            $validated['assignment_request_type'] = null;
            $validated['assignment_request_by_id'] = null;
            $validated['assignment_request_at'] = null;
        }

        $ticket->update($validated);

        if ($isAdmin && in_array((string) ($validated['estado'] ?? ''), ['finalizado', 'cerrado'], true)) {
            $this->closeActiveRemoteSessionsForTicket($ticket, 'La sesion remota se cerro automaticamente porque el ticket fue cerrado.');
        }

        if ($isAdmin && $previousAssigneeSignature !== $this->ticketAssignedEmployeeSignature($ticket)) {
            $assignedNames = $this->ticketAssignedEmployeeNames($ticket);
            $attendedByName = $assignedNames->isNotEmpty()
                ? $assignedNames->implode(', ')
                : 'Sin asignar';

            $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => 'Asignacion actualizada por ' . auth()->user()->name . '. Empleados asignados: ' . $attendedByName . '.',
                'tipo' => 'atencion',
            ]);

            app(TicketNotificationService::class)->notifyTicketAttended($ticket->fresh(['cliente', 'departamento', 'empleado']), $attendedByName);
        }

        return back()->with('success', 'Ticket actualizado correctamente.');
    }

    public function destroyTicket(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        if (!$this->canDeleteTicket($ticket)) {
            abort(403);
        }

        $ticket->estado = 'cerrado';
        $ticket->fecha_cierre = now();
        $ticket->save();
        $this->closeActiveRemoteSessionsForTicket($ticket, 'La sesion remota se cerro automaticamente porque el ticket fue eliminado.');
        $ticket->delete();

        return $this->ticketActionResponse($request, 'Ticket eliminado correctamente.');
    }

    public function toggleTicketCheckpoint(Request $request, int $ticket): RedirectResponse|JsonResponse
    {
        if (!auth()->user()->hasRole('Administrador')) {
            abort(403);
        }

        $ticketModel = Ticket::withTrashed()->findOrFail($ticket);

        if ($ticketModel->trashed()) {
            $ticketModel->restore();
            return $this->ticketActionResponse($request, 'Ticket habilitado correctamente.');
        }

        $ticketModel->estado = 'cerrado';
        $ticketModel->fecha_cierre = now();
        $ticketModel->save();
        $this->closeActiveRemoteSessionsForTicket($ticketModel, 'La sesion remota se cerro automaticamente porque el ticket fue deshabilitado.');
        $ticketModel->delete();

        return $this->ticketActionResponse($request, 'Ticket deshabilitado correctamente.');
    }

    public function finalizeTicket(Ticket $ticket): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if ($ticket->estado === 'finalizado') {
            return back()->with('success', 'El ticket ya estaba finalizado.');
        }

        if ($ticket->estado === 'cerrado') {
            return back()->with('error', 'El ticket ya fue cerrado y no se puede finalizar nuevamente.');
        }

        if (!$this->canFinalizeTicket($ticket)) {
            return back()->with('error', 'Solo se pueden finalizar tickets pendientes o en proceso.');
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

        app(TicketNotificationService::class)->notifyTicketFinalized($ticket, auth()->user()->name);

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
        $currentUser = $this->currentUser();

        if ($includeDeleted || ($currentUser && $currentUser->hasRole('Administrador'))) {
            $query->withTrashed();
        }

        if ($currentUser && $currentUser->hasRole('Usuario')) {
            $query->where(function ($ticketQuery) use ($currentUser): void {
                $ticketQuery->where('cliente_id', $currentUser->id);

                if (filled($currentUser->email)) {
                    $ticketQuery->orWhereHas('cliente', function ($clientQuery) use ($currentUser): void {
                        $clientQuery->where('email', $currentUser->email);
                    });
                }
            });
        }

        if ($currentUser && $currentUser->hasRole('Empleado')) {
            $employee = $this->currentEmployee();
            if ($employee) {
                $query->where(function ($q) use ($employee): void {
                    $q->where('empleado_id', $employee->id)
                      ->orWhereJsonContains('assigned_employee_ids', (int) $employee->id)
                      ->orWhere(function ($q2): void {
                          $q2->whereNull('empleado_id')
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
        return $this->ticketsQueryForCurrentUser()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');
    }

    private function menuBadges($statusCounts = null): array
    {
        if ($statusCounts !== null) {
            $counts = $statusCounts;
        } else {
            $userId = (int) ($this->currentUser()?->id ?? 0);
            if ($userId <= 0) {
                return ['pendientes' => 0];
            }

            $counts = Cache::remember(
                'menu_badges:ticket_status_counts:user:' . $userId,
                now()->addSeconds(20),
                fn () => $this->ticketStatusCounts()
            );
        }

        return [
            'pendientes' => (int) ($counts['pendiente'] ?? 0),
        ];
    }

    private function nextTicketCode(): string
    {
        $lastId = (int) Ticket::withTrashed()->max('id');

        return 'TCK-' . str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);
    }

    public function nextTicketCodeJson()
    {
        return response()->json(['codigo' => $this->nextTicketCode()]);
    }

    private function ticketActionResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    private function ticketAssignmentRequestResponse(Request $request, string $message, bool $success): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], $success ? 200 : 422);
        }

        return back()->with($success ? 'success' : 'error', $message);
    }

    private function ticketRatingResponse(Request $request, string $message, bool $success): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], $success ? 200 : 422);
        }

        return back()->with($success ? 'success' : 'error', $message);
    }

    private function refreshEmployeeRatingAverage(int $employeeId): void
    {
        if ($employeeId <= 0) {
            return;
        }

        $ratedTickets = Ticket::withTrashed()
            ->where('empleado_id', $employeeId)
            ->whereNotNull('atencion_puntuacion');

        $ratingCount = (int) (clone $ratedTickets)->count();
        $ratingAverage = $ratingCount > 0
            ? round((float) (clone $ratedTickets)->avg('atencion_puntuacion'), 2)
            : 0;

        User::query()
            ->whereKey($employeeId)
            ->update([
                'puntuacion_promedio' => $ratingAverage,
                'puntuaciones_count' => $ratingCount,
            ]);
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
        if ((string) $ticket->estado !== 'finalizado') {
            return false;
        }

        if (auth()->user()->hasRole('Administrador')) {
            return true;
        }

        if (auth()->user()->hasRole('Empleado')) {
            $employee = $this->currentEmployee();
            if (!$employee) {
                return false;
            }

            return $this->ticketHasEmployeeAssigned($ticket, (int) $employee->id);
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

    private function canEditTicket(Ticket $ticket): bool
    {
        if (auth()->user()->hasRole('Administrador')) {
            return $this->canAccessTicket($ticket);
        }

        return (string) $ticket->estado === 'pendiente' && $this->canAccessTicket($ticket);
    }

    private function canFinalizeTicket(Ticket $ticket): bool
    {
        if (auth()->user()->hasRole('Administrador')) {
            return in_array($ticket->estado, ['pendiente', 'en_proceso'], true);
        }

        if (!auth()->user()->hasRole('Empleado')) {
            return false;
        }

        $employee = $this->currentEmployee();
        if (!$employee) {
            return false;
        }

        return $this->ticketHasEmployeeAssigned($ticket, (int) $employee->id)
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

        $employee = $this->currentEmployee();
        if (!$employee) {
            return false;
        }

        return $this->ticketHasEmployeeAssigned($ticket, (int) $employee->id);
    }

    private function ticketHasEmployeeAssigned(Ticket $ticket, int $employeeId): bool
    {
        if ($employeeId <= 0) {
            return false;
        }

        if ((int) ($ticket->empleado_id ?? 0) === $employeeId) {
            return true;
        }

        return collect($ticket->assigned_employee_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->contains($employeeId);
    }

    private function ticketAssignedEmployeeIds(Ticket $ticket): array
    {
        return collect([$ticket->empleado_id])
            ->merge($ticket->assigned_employee_ids ?? [])
            ->filter(fn ($employeeId) => (int) $employeeId > 0)
            ->map(fn ($employeeId) => (int) $employeeId)
            ->unique()
            ->values()
            ->all();
    }

    private function ticketAssignedEmployeeNames(Ticket $ticket): \Illuminate\Support\Collection
    {
        $ids = $this->ticketAssignedEmployeeIds($ticket);

        if (empty($ids)) {
            return collect();
        }

        return Empleado::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Empleado $employee) => array_search((int) $employee->id, $ids, true))
            ->pluck('nombre_completo')
            ->values();
    }

    private function ticketAssignedEmployeeSignature(Ticket $ticket): string
    {
        return implode(',', $this->ticketAssignedEmployeeIds($ticket));
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

    private function rememberedAnyDeskSupportCodeForClient(int $clientId, ?int $exceptSessionId = null): string
    {
        if ($clientId <= 0 || !Schema::hasTable('ticket_eventos')) {
            return '';
        }

        $supportCode = TicketRemoteSession::query()
            ->whereNotNull('support_code')
            ->where('support_code', '!=', '')
            ->whereHas('ticket', function ($ticketQuery) use ($clientId): void {
                $ticketQuery->where('cliente_id', $clientId);
            })
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->latest('id')
            ->value('support_code');

        return (string) ($supportCode ?? '');
    }

    private function rememberedRustDeskCodeForClient(int $clientId, ?int $exceptSessionId = null): string
    {
        if (
            $clientId <= 0
            || !Schema::hasTable('ticket_eventos')
            || !Schema::hasColumn('ticket_eventos', 'rustdesk_code')
        ) {
            return '';
        }

        $rustDeskCode = TicketRemoteSession::query()
            ->whereNotNull('rustdesk_code')
            ->where('rustdesk_code', '!=', '')
            ->whereHas('ticket', function ($ticketQuery) use ($clientId): void {
                $ticketQuery->where('cliente_id', $clientId);
            })
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->latest('id')
            ->value('rustdesk_code');

        return (string) ($rustDeskCode ?? '');
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
Add-Type @"
using System;
using System.Runtime.InteropServices;
using System.Text;

public static class AnyDeskWindowHelper
{
    public delegate bool EnumWindowsProc(IntPtr hWnd, IntPtr lParam);

    [DllImport("user32.dll")]
    public static extern bool EnumWindows(EnumWindowsProc lpEnumFunc, IntPtr lParam);

    [DllImport("user32.dll")]
    public static extern bool IsWindowVisible(IntPtr hWnd);

    [DllImport("user32.dll", SetLastError = true)]
    public static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint processId);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    public static extern int GetWindowText(IntPtr hWnd, StringBuilder text, int maxCount);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    public static extern int GetClassName(IntPtr hWnd, StringBuilder className, int maxCount);

    [DllImport("user32.dll", SetLastError = true)]
    public static extern bool PostMessage(IntPtr hWnd, uint msg, IntPtr wParam, IntPtr lParam);
}
"@

$wmClose = 0x0010
$found = @(
    Get-Process -ErrorAction SilentlyContinue |
    Where-Object {
        $_.ProcessName -match '^(?i)anydesk' -or $_.ProcessName -match '^(?i)ad_svc$'
    }
)
$foundCount = $found.Count
$gracefulClosedCount = 0
$stoppedCount = 0
$targetProcessIds = @($found | ForEach-Object { [uint32] $_.Id })

if ($targetProcessIds.Count -gt 0) {
    $windowHandles = New-Object System.Collections.Generic.List[IntPtr]

    $null = [AnyDeskWindowHelper]::EnumWindows({
        param($hWnd, $lParam)

        if (-not [AnyDeskWindowHelper]::IsWindowVisible($hWnd)) {
            return $true
        }

        [uint32] $windowProcessId = 0
        $null = [AnyDeskWindowHelper]::GetWindowThreadProcessId($hWnd, [ref] $windowProcessId)

        if ($targetProcessIds -contains $windowProcessId) {
            $windowHandles.Add($hWnd) | Out-Null
            return $true
        }

        $titleBuilder = New-Object System.Text.StringBuilder 512
        $classBuilder = New-Object System.Text.StringBuilder 256
        $null = [AnyDeskWindowHelper]::GetWindowText($hWnd, $titleBuilder, $titleBuilder.Capacity)
        $null = [AnyDeskWindowHelper]::GetClassName($hWnd, $classBuilder, $classBuilder.Capacity)

        $title = $titleBuilder.ToString()
        $className = $classBuilder.ToString()

        if ($title -match '(?i)AnyDesk' -or $className -match '(?i)AnyDesk') {
            $windowHandles.Add($hWnd) | Out-Null
        }

        return $true
    }, [IntPtr]::Zero)

    foreach ($hWnd in $windowHandles) {
        try {
            if ([AnyDeskWindowHelper]::PostMessage($hWnd, $wmClose, [IntPtr]::Zero, [IntPtr]::Zero)) {
                $gracefulClosedCount++
            }
        } catch {
        }
    }

    if ($gracefulClosedCount -gt 0) {
        Start-Sleep -Milliseconds 700
    }
}

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

Write-Output "$foundCount|$gracefulClosedCount|$stoppedCount|$remainingCount"
POWERSHELL;

                $process = new Process(['powershell', '-NoProfile', '-Command', $script]);
                $process->setTimeout(10);
                $process->run();

                if (!$process->isSuccessful()) {
                    return false;
                }

                $result = trim((string) $process->getOutput());
                $parts = explode('|', $result);
                if (count($parts) !== 4) {
                    return false;
                }

                $foundCount = (int) $parts[0];
                $gracefulClosedCount = (int) $parts[1];
                $stoppedCount = (int) $parts[2];
                $remainingCount = (int) $parts[3];

                if ($foundCount === 0) {
                    return true;
                }

                return ($gracefulClosedCount > 0 || $stoppedCount > 0) && $remainingCount === 0;
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
        return Cache::remember('catalog:bolivia_departments:active', now()->addMinutes(10), function () {
            $departmentNames = $this->boliviaDepartmentNames();
            $departments = Departamento::query()
                ->whereIn('nombre', $departmentNames)
                ->where('activo', true)
                ->get();

            return $departments
                ->sortBy(fn (Departamento $departamento): int => (int) array_search($departamento->nombre, $departmentNames, true))
                ->values();
        });
    }

    private function ensureBoliviaDepartments(): void
    {
        $cacheKey = 'catalog:bolivia_departments:seeded';
        if (Cache::get($cacheKey)) {
            return;
        }

        $names = $this->boliviaDepartmentNames();
        $existingNames = Departamento::query()
            ->whereIn('nombre', $names)
            ->pluck('nombre')
            ->all();

        if (count($existingNames) === count($names)) {
            Cache::put($cacheKey, true, now()->addHours(12));
            return;
        }

        foreach ($names as $name) {
            $department = Departamento::query()->firstOrCreate(
                ['nombre' => $name],
                ['descripcion' => 'Departamento de Bolivia', 'activo' => true],
            );

            if (!$department->activo) {
                $department->forceFill(['activo' => true])->save();
            }
        }

        Cache::put($cacheKey, true, now()->addHours(12));
        Cache::forget('catalog:bolivia_departments:active');
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
        return Cache::remember('catalog:functional_work_areas:active', now()->addMinutes(10), fn () => AreaTrabajo::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get());
    }

    private function ensureDefaultWorkAreas(): void
    {
        $cacheKey = 'catalog:functional_work_areas:seeded';
        if (Cache::get($cacheKey)) {
            return;
        }

        $names = $this->defaultWorkAreaNames();
        $existingNames = AreaTrabajo::query()
            ->whereIn('nombre', $names)
            ->pluck('nombre')
            ->all();

        if (count($existingNames) === count($names)) {
            Cache::put($cacheKey, true, now()->addHours(12));
            return;
        }

        foreach ($names as $name) {
            AreaTrabajo::query()->firstOrCreate(
                ['nombre' => $name],
                ['descripcion' => 'Area de trabajo', 'activo' => true],
            );
        }

        Cache::put($cacheKey, true, now()->addHours(12));
        Cache::forget('catalog:functional_work_areas:active');
    }

    private function defaultWorkAreaNames(): array
    {
        return [
            'Sistemas',
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

        $employee = $this->currentEmployee();
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

    private function currentUser(): ?User
    {
        if ($this->authenticatedUserResolved) {
            return $this->authenticatedUser;
        }

        $this->authenticatedUserResolved = true;
        $user = auth()->user();
        $this->authenticatedUser = $user instanceof User ? $user : null;

        return $this->authenticatedUser;
    }

    private function currentEmployee(): ?Empleado
    {
        if ($this->authenticatedEmployeeResolved) {
            return $this->authenticatedEmployee;
        }

        $this->authenticatedEmployeeResolved = true;
        $currentUser = $this->currentUser();
        if (!$currentUser || !$currentUser->hasRole('Empleado')) {
            $this->authenticatedEmployee = null;

            return null;
        }

        $this->authenticatedEmployee = Empleado::with('departamentos')
            ->where(function ($query) use ($currentUser): void {
                $query->whereKey($currentUser->id);

                if (filled($currentUser->email)) {
                    $query->orWhere('email', $currentUser->email);
                }
            })
            ->first();

        return $this->authenticatedEmployee;
    }
}
