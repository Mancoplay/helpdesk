<?php

namespace App\Http\Controllers;

use App\Events\UserNotificationsUpdated;
use App\Models\Empleado;
use App\Models\Ticket;
use App\Services\NotificationSummaryService;
use App\Support\SafeBroadcast;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View|ViewContract|Response
    {
        $notifications = $this->notificationHistoryQuery($request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        if ($request->ajax()) {
            return response()->view('notifications.partials.history-card', [
                'notifications' => $notifications,
            ]);
        }

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function open(Request $request, string $notificationId): RedirectResponse
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->first();

        if (!$notification) {
            return back()->with('error', 'La notificación no existe o no te pertenece.');
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
            app(NotificationSummaryService::class)->forgetForUser($request->user());
            SafeBroadcast::dispatch(new UserNotificationsUpdated((int) $request->user()->id));
        }

        $targetResponse = $this->resolveNotificationRedirect($request, $notification);
        if ($targetResponse instanceof RedirectResponse) {
            return $targetResponse;
        }

        return redirect($targetResponse);
    }

    public function markAllAsRead(Request $request, NotificationSummaryService $notificationSummaryService): RedirectResponse|JsonResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);
        $notificationSummaryService->forgetForUser($request->user());
        SafeBroadcast::dispatch(new UserNotificationsUpdated((int) $request->user()->id));

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'summary' => $notificationSummaryService->forUser($request->user()),
            ]);
        }

        return back()->with('success', 'Notificaciones marcadas como leídas.');
    }

    public function unreadSummary(Request $request, NotificationSummaryService $notificationSummaryService): JsonResponse
    {
        $payload = $notificationSummaryService->forUser($request->user());

        return response()->json($payload);
    }

    private function notificationHistoryQuery(Request $request)
    {
        $retentionDays = max(1, (int) config('helpdesk.notifications.retention_days', 7));
        $cutoff = now()->subDays($retentionDays);

        return $request->user()
            ->notifications()
            ->where('created_at', '>=', $cutoff);
    }

    private function safeRedirectUrl(string $url): string
    {
        if ($url === '') {
            return route('dashboard');
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return route('dashboard');
        }

        if (!isset($parsed['host'])) {
            return str_starts_with($url, '/') ? $url : route('dashboard');
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (!is_string($appHost) || $appHost === '') {
            return route('dashboard');
        }

        return strcasecmp((string) $parsed['host'], $appHost) === 0
            ? $url
            : route('dashboard');
    }

    private function resolveNotificationRedirect(Request $request, DatabaseNotification $notification): string|RedirectResponse
    {
        $data = (array) $notification->data;
        $ticketId = (int) ($data['ticket_id'] ?? 0);

        if ($ticketId <= 0) {
            return $this->safeRedirectUrl((string) ($data['url'] ?? route('dashboard')));
        }

        $ticket = Ticket::query()
            ->withTrashed()
            ->with(['cliente'])
            ->find($ticketId);

        if (!$ticket || $ticket->trashed()) {
            return redirect()
                ->route('notifications.index')
                ->with('error', 'El ticket ya no esta disponible.');
        }

        $user = $request->user();

        if ($user->hasRole('Administrador')) {
            return route('tickets.show', $ticket);
        }

        if ($user->hasRole('Empleado')) {
            $employee = Empleado::query()
                ->whereKey($user->id)
                ->orWhere('email', $user->email)
                ->first();

            if (!$employee) {
                return redirect()
                    ->route('notifications.index')
                    ->with('error', 'No se pudo identificar al empleado.');
            }

            $isAssignedToCurrentEmployee = (int) ($ticket->empleado_id ?? 0) === (int) $employee->id;
            $isPendingUnassigned = $ticket->estado === 'pendiente' && is_null($ticket->empleado_id);

            if (!$isAssignedToCurrentEmployee && !$isPendingUnassigned) {
                return redirect()
                    ->route('tickets.index')
                    ->with('error', 'El ticket ya esta siendo atendido.');
            }

            return route('tickets.show', $ticket);
        }

        $isClientOwner = (int) ($ticket->cliente->id ?? 0) === (int) $user->id
            || (($ticket->cliente->email ?? null) === $user->email);

        if (!$isClientOwner) {
            return redirect()
                ->route('notifications.index')
                ->with('error', 'No tienes acceso a este ticket.');
        }

        return route('tickets.show', $ticket);
    }
}
