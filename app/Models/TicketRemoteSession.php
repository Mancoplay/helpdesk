<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRemoteSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'requested_by_user_id',
        'cancelled_by_user_id',
        'status',
        'support_code',
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

