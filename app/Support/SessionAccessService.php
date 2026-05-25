<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SessionAccessService
{
    public function shouldEnforceSingleLogin(): bool
    {
        return (bool) config('session.enforce_single_login', false);
    }

    public function clearExpiredSessionsForUser(int $userId, string $currentSessionId = ''): void
    {
        if ($userId <= 0 || config('session.driver') !== 'database') {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('last_activity', '<', $this->activeCutoffTimestamp());

        if ($currentSessionId !== '') {
            $query->where('id', '!=', $currentSessionId);
        }

        $query->delete();
    }

    public function clearRecoverableSessionsForClient(
        int $userId,
        string $currentSessionId = '',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        if ($userId <= 0 || config('session.driver') !== 'database') {
            return;
        }

        if (blank($ipAddress) || blank($userAgent)) {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('ip_address', $ipAddress)
            ->where('user_agent', $userAgent);

        if ($currentSessionId !== '') {
            $query->where('id', '!=', $currentSessionId);
        }

        $query->delete();
    }

    public function hasAnotherActiveSession(int $userId, string $currentSessionId = ''): bool
    {
        if ($userId <= 0 || config('session.driver') !== 'database') {
            return false;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $this->activeCutoffTimestamp());

        if ($currentSessionId !== '') {
            $query->where('id', '!=', $currentSessionId);
        }

        return $query->exists();
    }

    public function clearOtherSessions(int $userId, string $currentSessionId = ''): void
    {
        if ($userId <= 0 || config('session.driver') !== 'database') {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId);

        if ($currentSessionId !== '') {
            $query->where('id', '!=', $currentSessionId);
        }

        $query->delete();
    }

    public function clearSessionById(?string $sessionId): void
    {
        if (blank($sessionId) || config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->delete();
    }

    private function activeCutoffTimestamp(): int
    {
        $activeWindowSeconds = (int) config('session.concurrent_window_seconds', 0);

        if ($activeWindowSeconds <= 0) {
            $activeWindowSeconds = max(
                60,
                min(
                    (int) config('session.lifetime', 120) * 60,
                    (int) config('session.concurrent_window', 2) * 60
                )
            );
        }

        return time() - $activeWindowSeconds;
    }
}
