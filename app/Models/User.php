<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'nombres',
        'apellidos',
        'email',
        'password',
        'telefono',
        'direccion',
        'empresa',
        'cargo',
        'activo',
        'departamento_id',
        'area_trabajo_id',
        'puntuacion_promedio',
        'puntuaciones_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'puntuacion_promedio' => 'decimal:2',
            'puntuaciones_count' => 'integer',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        $fullName = trim(collect([$this->nombres, $this->apellidos])->filter()->implode(' '));
        if ($fullName !== '') {
            return $fullName;
        }

        return $this->name;
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_empleado', 'user_id', 'departamento_id')
            ->withTimestamps();
    }

    public function areaTrabajo(): BelongsTo
    {
        return $this->belongsTo(AreaTrabajo::class, 'area_trabajo_id');
    }

    public function ticketsComoCliente(): HasMany
    {
        return $this->hasMany(Ticket::class, 'cliente_id');
    }

    public function ticketsComoEmpleado(): HasMany
    {
        return $this->hasMany(Ticket::class, 'empleado_id');
    }
}
