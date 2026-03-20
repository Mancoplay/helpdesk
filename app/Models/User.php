<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    public function empleado(): HasOne
    {
        return $this->hasOne(Empleado::class);
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class, 'email', 'email');
    }

    public function getNombreCompletoAttribute(): string
    {
        $empleado = $this->empleado;
        if ($empleado && !empty($empleado->nombre_completo)) {
            return $empleado->nombre_completo;
        }

        $cliente = $this->cliente;
        if ($cliente && !empty($cliente->nombre_completo)) {
            return $cliente->nombre_completo;
        }

        return $this->name;
    }
}
