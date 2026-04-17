<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
