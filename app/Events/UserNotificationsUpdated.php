<?php

namespace App\Events;

use App\Models\User;
use App\Services\NotificationSummaryService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.' . $this->userId . '.notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notifications.updated';
    }

    public function broadcastWith(): array
    {
        $summary = null;
        $user = User::query()->find($this->userId);

        if ($user) {
            $summary = app(NotificationSummaryService::class)->forUser($user, false);
        }

        return [
            'user_id' => $this->userId,
            'occurred_at' => now()->toIso8601String(),
            'summary' => $summary,
        ];
    }
}
