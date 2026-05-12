<?php

namespace App\Models;

use App\Events\TicketListUpdated;
use App\Events\TicketStreamUpdated;
use App\Support\SafeBroadcast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRemoteSession extends Model
{
    use HasFactory;

    protected $table = 'ticket_eventos';

    protected $fillable = [
        'event_type',
        'ticket_id',
        'requested_by_user_id',
        'cancelled_by_user_id',
        'status',
        'support_code',
        'rustdesk_code',
        'requested_at',
        'responded_at',
        'ended_at',
        'note',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('remote_event', function ($query): void {
            $query->where('event_type', 'remote');
        });

        static::creating(function (TicketRemoteSession $session): void {
            if (blank($session->event_type)) {
                $session->event_type = 'remote';
            }
        });

        static::saved(function (TicketRemoteSession $session): void {
            SafeBroadcast::dispatch(new TicketStreamUpdated((int) $session->ticket_id));

            $ticket = $session->relationLoaded('ticket')
                ? $session->ticket
                : $session->ticket()->withTrashed()->first();

            if ($ticket) {
                SafeBroadcast::dispatch(new TicketListUpdated(
                    (int) $ticket->id,
                    ($ticket->cliente_id ?? 0) > 0 ? (int) $ticket->cliente_id : null,
                ));
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
