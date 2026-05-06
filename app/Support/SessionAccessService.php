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
        $activeWindowMinutes = max(
            1,
            min(
                (int) config('session.lifetime', 120),
                (int) config('session.concurrent_window', 2)
            )
        );

        return time() - ($activeWindowMinutes * 60);
    }
}
