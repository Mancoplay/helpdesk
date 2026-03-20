<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMensaje extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'mensaje',
        'imagen_path',
        'imagen_nombre',
        'imagen_mime',
        'imagen_size',
        'tipo',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
