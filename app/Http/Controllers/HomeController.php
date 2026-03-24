<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Ticket;
use App\Models\TicketRemoteSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
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

    public function usuarios(Request $request)
    {
        [$searchQuery, $perPage] = $this->resolveTableFilters($request);

        return view('usuarios.index', [
            'usuarios' => User::query()
                ->when($searchQuery !== '', function (Builder $query) use ($searchQuery): void {
                    $query->where(function (Builder $subQuery) use ($searchQuery): void {
                        $subQuery->where('name', 'like', "%{$searchQuery}%")
                            ->orWhere('email', 'like', "%{$searchQuery}%");
                    });
                })
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
            'searchQuery' => $searchQuery,
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
        [$searchQuery, $perPage] = $this->resolveTableFilters($request);

        return view('clientes.index', [
            'clientes' => Cliente::query()
                ->when($searchQuery !== '', function (Builder $query) use ($searchQuery): void {
                    $query->where(function (Builder $subQuery) use ($searchQuery): void {
                        $subQuery->where('nombres', 'like', "%{$searchQuery}%")
                            ->orWhere('segundo_nombre', 'like', "%{$searchQuery}%")
                            ->orWhere('apellidos', 'like', "%{$searchQuery}%")
                            ->orWhere('email', 'like', "%{$searchQuery}%")
                            ->orWhere('telefono', 'like', "%{$searchQuery}%")
                            ->orWhere('empresa', 'like', "%{$searchQuery}%")
                            ->orWhere('direccion', 'like', "%{$searchQuery}%");
                    });
                })
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
            'searchQuery' => $searchQuery,
            'perPage' => $perPage,
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

    public function reviewCliente(Request $request, Cliente $cliente)
    {
        [$period, $fromDate, $toDate, $fromInput, $toInput] = $this->resolveHistoryDateRange($request);

        $ticketsBase = Ticket::withTrashed()
            ->with(['empleado', 'departamento'])
            ->where('cliente_id', $cliente->id);

        $this->applyDateRangeFilter($ticketsBase, $fromDate, $toDate);

        $tickets = (clone $ticketsBase)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'total_tickets' => (clone $ticketsBase)->count(),
            'tickets_eliminados' => (clone $ticketsBase)->onlyTrashed()->count(),
            'tickets_cerrados' => (clone $ticketsBase)->whereIn('estado', ['cerrado', 'finalizado'])->count(),
            'empleados_distintos' => (clone $ticketsBase)->whereNotNull('empleado_id')->distinct()->count('empleado_id'),
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

    public function empleados(Request $request)
    {
        [$searchQuery, $perPage] = $this->resolveTableFilters($request);

        return view('empleados.index', [
            'empleados' => Empleado::query()
                ->with(['departamento', 'departamentos'])
                ->when($searchQuery !== '', function (Builder $query) use ($searchQuery): void {
                    $query->where(function (Builder $subQuery) use ($searchQuery): void {
                        $subQuery->where('nombres', 'like', "%{$searchQuery}%")
                            ->orWhere('segundo_nombre', 'like', "%{$searchQuery}%")
                            ->orWhere('apellidos', 'like', "%{$searchQuery}%")
                            ->orWhere('email', 'like', "%{$searchQuery}%")
                            ->orWhere('telefono', 'like', "%{$searchQuery}%")
                            ->orWhere('cargo', 'like', "%{$searchQuery}%")
                            ->orWhereHas('departamento', function (Builder $departmentQuery) use ($searchQuery): void {
                                $departmentQuery->where('nombre', 'like', "%{$searchQuery}%");
                            })
                            ->orWhereHas('departamentos', function (Builder $departmentsQuery) use ($searchQuery): void {
                                $departmentsQuery->where('nombre', 'like', "%{$searchQuery}%");
                            });
                    });
                })
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'searchQuery' => $searchQuery,
            'perPage' => $perPage,
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeEmpleado(Request $request): RedirectResponse
    {
        $departamentoInput = $request->input('departamento_ids', []);
        if (!is_array($departamentoInput)) {
            $departamentoInput = [$departamentoInput];
        }
        if ($request->filled('departamento_id')) {
            $departamentoInput[] = $request->input('departamento_id');
        }
        $request->merge([
            'departamento_ids' => collect($departamentoInput)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:empleados,email', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'departamento_ids' => ['required', 'array', 'min:1'],
            'departamento_ids.*' => ['exists:departamentos,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $departamentoIds = collect($validated['departamento_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $departamentoPrincipal = $departamentoIds[0] ?? null;

        $user = User::create([
            'name' => trim($validated['nombres'] . ' ' . ($validated['apellidos'] ?? '')),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->syncRoles(['Empleado']);

        $empleado = Empleado::create([
            'user_id' => $user->id,
            'departamento_id' => $departamentoPrincipal,
            'nombres' => $validated['nombres'],
            'segundo_nombre' => $validated['segundo_nombre'] ?? null,
            'apellidos' => $validated['apellidos'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
            'activo' => true,
        ]);

        $empleado->departamentos()->sync($departamentoIds);

        return back()->with('success', 'Empleado agregado correctamente.');
    }

    public function updateEmpleado(Request $request, Empleado $empleado): RedirectResponse
    {
        $departamentoInput = $request->input('departamento_ids', []);
        if (!is_array($departamentoInput)) {
            $departamentoInput = [$departamentoInput];
        }
        if ($request->filled('departamento_id')) {
            $departamentoInput[] = $request->input('departamento_id');
        }
        $request->merge([
            'departamento_ids' => collect($departamentoInput)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('empleados', 'email')->ignore($empleado->id),
                Rule::unique('users', 'email')->ignore($empleado->user_id),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'departamento_ids' => ['required', 'array', 'min:1'],
            'departamento_ids.*' => ['exists:departamentos,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $departamentoIds = collect($validated['departamento_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $departamentoPrincipal = $departamentoIds[0] ?? null;

        $empleado->update([
            'departamento_id' => $departamentoPrincipal,
            'nombres' => $validated['nombres'],
            'segundo_nombre' => $validated['segundo_nombre'] ?? null,
            'apellidos' => $validated['apellidos'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
        ]);

        $empleado->departamentos()->sync($departamentoIds);

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

    public function reviewEmpleado(Request $request, Empleado $empleado)
    {
        $empleado->loadMissing(['departamento', 'departamentos']);

        [$period, $fromDate, $toDate, $fromInput, $toInput] = $this->resolveHistoryDateRange($request);

        $ticketsBase = Ticket::withTrashed()
            ->with(['cliente', 'departamento'])
            ->where('empleado_id', $empleado->id);

        $this->applyDateRangeFilter($ticketsBase, $fromDate, $toDate);

        $tickets = (clone $ticketsBase)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'total_tickets' => (clone $ticketsBase)->count(),
            'tickets_eliminados' => (clone $ticketsBase)->onlyTrashed()->count(),
            'tickets_cerrados' => (clone $ticketsBase)->whereIn('estado', ['cerrado', 'finalizado'])->count(),
            'clientes_atendidos' => (clone $ticketsBase)->whereNotNull('cliente_id')->distinct()->count('cliente_id'),
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

    public function departamentos(Request $request)
    {
        [$searchQuery, $perPage] = $this->resolveTableFilters($request);

        return view('departamentos.index', [
            'departamentos' => Departamento::query()
                ->when($searchQuery !== '', function (Builder $query) use ($searchQuery): void {
                    $query->where(function (Builder $subQuery) use ($searchQuery): void {
                        $subQuery->where('nombre', 'like', "%{$searchQuery}%")
                            ->orWhere('descripcion', 'like', "%{$searchQuery}%");
                    });
                })
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
            'searchQuery' => $searchQuery,
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
        [$searchQuery, $perPage] = $this->resolveTableFilters($request);

        $tickets = $this->ticketsQueryForCurrentUser()
            ->with(['cliente', 'empleado', 'departamento'])
            ->when($searchQuery !== '', function (Builder $query) use ($searchQuery): void {
                $query->where(function (Builder $subQuery) use ($searchQuery): void {
                    $subQuery->where('codigo', 'like', "%{$searchQuery}%")
                        ->orWhere('asunto', 'like', "%{$searchQuery}%")
                        ->orWhere('descripcion', 'like', "%{$searchQuery}%")
                        ->orWhere('estado', 'like', "%{$searchQuery}%")
                        ->orWhere('prioridad', 'like', "%{$searchQuery}%")
                        ->orWhereHas('cliente', function (Builder $clientQuery) use ($searchQuery): void {
                            $clientQuery->where('nombres', 'like', "%{$searchQuery}%")
                                ->orWhere('apellidos', 'like', "%{$searchQuery}%")
                                ->orWhere('email', 'like', "%{$searchQuery}%");
                        })
                        ->orWhereHas('empleado', function (Builder $employeeQuery) use ($searchQuery): void {
                            $employeeQuery->where('nombres', 'like', "%{$searchQuery}%")
                                ->orWhere('apellidos', 'like', "%{$searchQuery}%")
                                ->orWhere('email', 'like', "%{$searchQuery}%");
                        })
                        ->orWhereHas('departamento', function (Builder $departmentQuery) use ($searchQuery): void {
                            $departmentQuery->where('nombre', 'like', "%{$searchQuery}%");
                        });
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $currentEmployee = null;
        if (auth()->user()->hasRole('Empleado')) {
            $currentEmployee = Empleado::where('user_id', auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->with('departamentos')
                ->first();
        }

        return view('tickets.index', [
            'tickets' => $tickets,
            'clientes' => Cliente::orderBy('nombres')->orderBy('apellidos')->get(),
            'empleados' => Empleado::orderBy('nombres')->orderBy('apellidos')->get(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'departamentosActivos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'nextTicketCode' => $this->nextTicketCode(),
            'currentEmployeeId' => $currentEmployee?->id,
            'searchQuery' => $searchQuery,
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

        return view('tickets.show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'remoteEnabled' => $this->isRemoteSessionsReady(),
            'remoteSession' => $this->isRemoteSessionsReady()
                ? $ticket->remoteSessions()->latest()->first()
                : null,
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'menuBadges' => $this->menuBadges(),
        ]);
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => ['nullable', 'string', 'max:25'],
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

        $validated['estado'] = 'pendiente';
        $validated['fecha_cierre'] = null;
        $validated['codigo'] = $this->nextAvailableTicketCode($validated['codigo'] ?? null);

        $ticket = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $ticket = Ticket::create($validated);
                break;
            } catch (QueryException $exception) {
                if (!$this->isDuplicateTicketCodeException($exception)) {
                    throw $exception;
                }

                $validated['codigo'] = $this->nextAvailableTicketCode($validated['codigo']);
            }
        }

        if (!$ticket) {
            return back()->with('error', 'No se pudo generar un codigo de ticket unico. Intenta nuevamente.');
        }

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => $validated['descripcion'],
            'tipo' => 'creacion',
        ]);

        return back()->with('success', 'Ticket agregado correctamente.');
    }

    public function nextTicketCodeJson(): JsonResponse
    {
        return response()->json([
            'codigo' => $this->nextTicketCode(),
        ]);
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

        if (auth()->user()->hasRole('Empleado') && $ticket->estado === 'pendiente') {
            return back()->with('error', 'Debes atender el ticket antes de enviar mensajes o imagenes.');
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

        return back();
    }

    public function requestRemoteSession(Ticket $ticket): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!$this->isRemoteSessionsReady()) {
            return back()->with('error', 'La funcionalidad remota aun no esta disponible. Ejecuta las migraciones pendientes.');
        }

        if ($ticket->estado !== 'en_proceso') {
            return back()->with('error', 'Solo puedes iniciar conexion remota cuando el ticket esta en proceso.');
        }

        $employee = Empleado::where('user_id', auth()->id())
            ->orWhere('email', auth()->user()->email)
            ->first();

        if (!$employee || (int) $ticket->empleado_id !== (int) $employee->id) {
            return back()->with('error', 'Solo el empleado asignado puede solicitar conexion remota.');
        }

        $existing = $ticket->remoteSessions()
            ->whereIn('status', ['pending', 'accepted'])
            ->latest()
            ->first();

        if ($existing) {
            return back()->with('error', 'Ya existe una solicitud de conexion remota activa para este ticket.');
        }

        $ticket->remoteSessions()->create([
            'requested_by_user_id' => auth()->id(),
            'status' => 'pending',
            'support_code' => null,
            'requested_at' => now(),
            'note' => json_encode(['tool' => null], JSON_UNESCAPED_UNICODE),
        ]);

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => 'Solicitud de conexion remota enviada. El cliente debe compartir su codigo de AnyDesk.',
            'tipo' => 'atencion',
        ]);

        return back()->with('success', 'Solicitud de conexion remota enviada al cliente.');
    }

    public function updateRemoteSession(Request $request, Ticket $ticket, TicketRemoteSession $remoteSession): RedirectResponse
    {
        if (!$this->canAccessTicket($ticket)) {
            abort(403);
        }

        if (!$this->isRemoteSessionsReady()) {
            return back()->with('error', 'La funcionalidad remota aun no esta disponible. Ejecuta las migraciones pendientes.');
        }

        if ((int) $remoteSession->ticket_id !== (int) $ticket->id) {
            abort(404);
        }

        $action = (string) $request->input('action');
        if (!in_array($action, ['accept', 'reject', 'cancel', 'end', 'share_code'], true)) {
            return back()->with('error', 'Accion no valida.');
        }

        $isClientOwner = auth()->user()->hasRole('Usuario')
            && ($ticket->cliente->email ?? null) === auth()->user()->email;

        $employee = Empleado::where('user_id', auth()->id())
            ->orWhere('email', auth()->user()->email)
            ->first();

        $isAssignedEmployee = $employee && (int) $ticket->empleado_id === (int) $employee->id;

        if ($ticket->estado !== 'en_proceso') {
            return back()->with('error', 'La conexion remota solo esta habilitada cuando el ticket esta en proceso.');
        }

        if (in_array($action, ['accept', 'reject', 'share_code'], true) && !$isClientOwner) {
            return back()->with('error', 'Solo el cliente del ticket puede responder esta solicitud.');
        }

        if (in_array($action, ['cancel', 'end'], true) && !$isClientOwner && !$isAssignedEmployee) {
            return back()->with('error', 'Solo el cliente o el empleado asignado pueden cancelar/finalizar.');
        }

        if ($action === 'accept' && $remoteSession->status !== 'pending') {
            return back()->with('error', 'La solicitud ya no se encuentra pendiente.');
        }

        if ($action === 'reject' && $remoteSession->status !== 'pending') {
            return back()->with('error', 'La solicitud ya no se encuentra pendiente.');
        }

        if ($action === 'cancel' && !in_array($remoteSession->status, ['pending', 'accepted'], true)) {
            return back()->with('error', 'No se puede cancelar una solicitud finalizada.');
        }

        if ($action === 'end' && $remoteSession->status !== 'accepted') {
            return back()->with('error', 'Solo se puede finalizar una sesion aceptada.');
        }

        if ($action === 'share_code' && !in_array($remoteSession->status, ['pending', 'accepted'], true)) {
            return back()->with('error', 'Solo puedes compartir codigo en una solicitud activa.');
        }

        if ($action === 'accept') {
            $remoteSession->status = 'accepted';
            $remoteSession->responded_at = now();
            $remoteSession->save();

            $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => 'El cliente acepto la conexion remota.',
                'tipo' => 'atencion',
            ]);

            return back()->with('success', 'Conexion remota aceptada.');
        }

        if ($action === 'reject') {
            $remoteSession->status = 'rejected';
            $remoteSession->responded_at = now();
            $remoteSession->save();

            $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => 'El cliente rechazo la conexion remota.',
                'tipo' => 'atencion',
            ]);

            return back()->with('success', 'Conexion remota rechazada.');
        }

        if ($action === 'cancel') {
            $remoteSession->status = 'cancelled';
            $remoteSession->cancelled_by_user_id = auth()->id();
            $remoteSession->ended_at = now();
            $remoteSession->save();

            $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => 'La solicitud de conexion remota fue cancelada.',
                'tipo' => 'atencion',
            ]);

            return back()->with('success', 'Solicitud de conexion remota cancelada.');
        }

        if ($action === 'share_code') {
            $validated = $request->validate([
                'support_code' => ['required', 'string', 'max:40'],
            ]);

            $supportCode = trim((string) $validated['support_code']);
            if ($supportCode === '') {
                return back()->with('error', 'Debes ingresar el codigo de AnyDesk.');
            }

            $remoteMeta = [];
            if (is_string($remoteSession->note) && $remoteSession->note !== '') {
                $decoded = json_decode($remoteSession->note, true);
                if (is_array($decoded)) {
                    $remoteMeta = $decoded;
                }
            }

            $remoteMeta['tool'] = 'anydesk';
            $remoteSession->support_code = $supportCode;
            $remoteSession->note = json_encode($remoteMeta, JSON_UNESCAPED_UNICODE);
            if ($remoteSession->status === 'pending') {
                $remoteSession->status = 'accepted';
                $remoteSession->responded_at = now();
            }
            $remoteSession->save();

            $ticket->mensajes()->create([
                'user_id' => auth()->id(),
                'mensaje' => 'Cliente compartio codigo de AnyDesk para soporte remoto.',
                'tipo' => 'atencion',
            ]);

            return back()->with('success', 'Codigo de AnyDesk enviado correctamente.');
        }

        $remoteSession->status = 'ended';
        $remoteSession->ended_at = now();
        $remoteSession->save();

        $ticket->mensajes()->create([
            'user_id' => auth()->id(),
            'mensaje' => 'La sesion de conexion remota fue finalizada.',
            'tipo' => 'atencion',
        ]);

        return back()->with('success', 'Sesion remota finalizada.');
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
                ->with('departamentos:id')
                ->first();

            if ($employee) {
                $departmentIds = $employee->departamentos->pluck('id')
                    ->push((int) $employee->departamento_id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $query->where(function ($q) use ($employee, $departmentIds): void {
                    $q->where('empleado_id', $employee->id)
                      ->orWhere(function ($q2) use ($departmentIds): void {
                          $q2->whereNull('empleado_id')
                             ->where('estado', 'pendiente')
                             ->whereIn('departamento_id', $departmentIds);
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

    private function resolveTableFilters(Request $request): array
    {
        $allowedPerPage = [10, 15, 20];
        $perPage = (int) $request->query('per_page', 10);

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $searchQuery = trim((string) $request->query('q', ''));

        return [$searchQuery, $perPage];
    }

    private function resolveHistoryDateRange(Request $request): array
    {
        $period = (string) $request->query('period', 'month');
        if (!in_array($period, ['week', 'month', 'year', 'custom'], true)) {
            $period = 'month';
        }

        $now = now();
        $from = null;
        $to = null;
        $fromInput = (string) $request->query('from', '');
        $toInput = (string) $request->query('to', '');

        if ($period === 'custom' && $fromInput !== '' && $toInput !== '') {
            try {
                $from = Carbon::parse($fromInput)->startOfDay();
                $to = Carbon::parse($toInput)->endOfDay();
            } catch (\Throwable $exception) {
                $period = 'month';
            }
        }

        if (!$from || !$to) {
            if ($period === 'week') {
                $from = $now->copy()->startOfWeek();
                $to = $now->copy()->endOfWeek();
            } elseif ($period === 'year') {
                $from = $now->copy()->startOfYear();
                $to = $now->copy()->endOfYear();
            } else {
                $period = 'month';
                $from = $now->copy()->startOfMonth();
                $to = $now->copy()->endOfMonth();
            }
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$period, $from, $to, $fromInput, $toInput];
    }

    private function applyDateRangeFilter(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }

    private function nextTicketCode(): string
    {
        $maxNumber = (int) Ticket::withTrashed()
            ->where('codigo', 'like', 'TCK-%')
            ->selectRaw('MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)) as max_code')
            ->value('max_code');

        $nextNumber = max(1, $maxNumber + 1);

        return 'TCK-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function nextAvailableTicketCode(?string $requestedCode): string
    {
        $candidate = trim((string) $requestedCode);
        if ($candidate === '') {
            $candidate = $this->nextTicketCode();
        }

        while (Ticket::withTrashed()->where('codigo', $candidate)->exists()) {
            $candidate = $this->incrementTicketCode($candidate);
        }

        return $candidate;
    }

    private function incrementTicketCode(string $currentCode): string
    {
        if (!preg_match('/^TCK-(\d+)$/', $currentCode, $matches)) {
            return $this->nextTicketCode();
        }

        $nextNumber = (int) $matches[1] + 1;

        return 'TCK-' . str_pad((string) $nextNumber, max(strlen($matches[1]), 4), '0', STR_PAD_LEFT);
    }

    private function isDuplicateTicketCodeException(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $sqlState = (string) ($errorInfo[0] ?? '');
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $message = strtolower((string) ($errorInfo[2] ?? $exception->getMessage()));

        return ($sqlState === '23000' && $driverCode === 1062)
            || str_contains($message, 'duplicate')
            || str_contains($message, 'duplic')
            || str_contains($message, 'unique')
            || str_contains($message, 'tickets_codigo_unique')
            || str_contains($message, 'tickets.codigo');
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

    private function isRemoteSessionsReady(): bool
    {
        return Schema::hasTable('ticket_remote_sessions');
    }

}

