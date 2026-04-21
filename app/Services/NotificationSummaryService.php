<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

class NotificationSummaryService
{
    public function forUser(User $user, bool $useCache = true): array
    {
        $cacheKey = $this->cacheKey((int) $user->id);

        if (!$useCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($user): array {
            $latestUnread = $user->unreadNotifications()
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

            return [
                'count' => (int) $user->unreadNotifications()->count(),
                'items' => $latestUnread,
            ];
        });
    }

    public function forgetForUser(User $user): void
    {
        Cache::forget($this->cacheKey((int) $user->id));
    }

    private function cacheKey(int $userId): string
    {
        return 'notifications:summary:' . $userId;
    }
}
