<?php

namespace App\Models;

use App\Events\TicketStreamUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMensaje extends Model
{
    use HasFactory;

    protected $table = 'ticket_eventos';

    protected $fillable = [
        'event_type',
        'ticket_id',
        'user_id',
        'mensaje',
        'imagen_path',
        'imagen_nombre',
        'imagen_mime',
        'imagen_size',
        'tipo',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('mensaje_event', function ($query): void {
            $query->where('event_type', 'mensaje');
        });

        static::creating(function (TicketMensaje $mensaje): void {
            if (blank($mensaje->event_type)) {
                $mensaje->event_type = 'mensaje';
            }
        });

        static::created(function (TicketMensaje $mensaje): void {
            event(new TicketStreamUpdated((int) $mensaje->ticket_id));
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
