<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombres',
        'segundo_nombre',
        'apellidos',
        'email',
        'telefono',
        'direccion',
        'empresa',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(collect([$this->nombres, $this->segundo_nombre, $this->apellidos])->filter()->implode(' '));
    }
}
