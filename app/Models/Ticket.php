<?php

namespace App\Models;

use App\Events\TicketListUpdated;
use App\Events\TicketStreamUpdated;
use App\Support\SafeBroadcast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'codigo',
        'cliente_id',
        'empleado_id',
        'departamento_id',
        'asunto',
        'descripcion',
        'estado',
        'fecha_cierre',
        'atencion_puntuacion',
        'puntuado_por_id',
        'puntuado_at',
        'last_notified_at',
    ];

    protected $casts = [
        'fecha_cierre' => 'datetime',
        'atencion_puntuacion' => 'integer',
        'puntuado_at' => 'datetime',
        'last_notified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Ticket $ticket): void {
            $ticket->broadcastListUpdate();
        });

        static::updated(function (Ticket $ticket): void {
            if ($ticket->wasChanged(['estado', 'empleado_id', 'departamento_id', 'asunto', 'descripcion'])) {
                SafeBroadcast::dispatch(new TicketStreamUpdated((int) $ticket->id));
            }

            if ($ticket->wasChanged(['cliente_id', 'estado', 'empleado_id', 'departamento_id', 'asunto', 'descripcion', 'fecha_cierre'])) {
                $ticket->broadcastListUpdate();
            }
        });

        static::deleted(function (Ticket $ticket): void {
            $ticket->broadcastListUpdate();
        });

        static::restored(function (Ticket $ticket): void {
            $ticket->broadcastListUpdate();
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(TicketMensaje::class);
    }

    public function remoteSessions(): HasMany
    {
        return $this->hasMany(TicketRemoteSession::class);
    }

    public function broadcastListUpdate(): void
    {
        SafeBroadcast::dispatch(new TicketListUpdated(
            (int) $this->id,
            ($this->cliente_id ?? 0) > 0 ? (int) $this->cliente_id : null,
        ));
    }
}
