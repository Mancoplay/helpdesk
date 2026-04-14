<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
            return back()->with('error', 'La notificacion no existe o no te pertenece.');
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $url = $notification->data['url'] ?? route('dashboard');

        return redirect($url);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with('success', 'Notificaciones marcadas como leidas.');
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        $latestUnread = $request->user()
            ->unreadNotifications()
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (DatabaseNotification $notification): array {
                return [
                    'id' => $notification->id,
                    'title' => (string) ($notification->data['title'] ?? 'Notificacion'),
                    'message' => (string) ($notification->data['message'] ?? ''),
                    'created_human' => (string) optional($notification->created_at)->diffForHumans(),
                    'open_url' => route('notifications.open', $notification->id),
                ];
            })
            ->values();

        return response()->json([
            'count' => (int) $request->user()->unreadNotifications()->count(),
            'items' => $latestUnread,
        ]);
    }

    private function notificationHistoryQuery(Request $request)
    {
        $retentionDays = max(1, (int) config('helpdesk.notifications.retention_days', 30));
        $cutoff = now()->subDays($retentionDays);

        return $request->user()
            ->notifications()
            ->where('created_at', '>=', $cutoff);
    }
}
