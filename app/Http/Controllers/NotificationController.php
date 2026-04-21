<?php

namespace App\Http\Controllers;

use App\Events\UserNotificationsUpdated;
use App\Services\NotificationSummaryService;
use App\Support\SafeBroadcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $this->notificationHistoryQuery($request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

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

        $url = (string) ($notification->data['url'] ?? route('dashboard'));
        $url = $this->safeRedirectUrl($url);

        return redirect($url);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);
        app(NotificationSummaryService::class)->forgetForUser($request->user());
        SafeBroadcast::dispatch(new UserNotificationsUpdated((int) $request->user()->id));

        return back()->with('success', 'Notificaciones marcadas como leídas.');
    }

    public function unreadSummary(Request $request, NotificationSummaryService $notificationSummaryService): JsonResponse
    {
        $payload = $notificationSummaryService->forUser($request->user());

        return response()->json($payload);
    }

    private function notificationHistoryQuery(Request $request)
    {
        $retentionDays = max(1, (int) config('helpdesk.notifications.retention_days', 30));
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
}
