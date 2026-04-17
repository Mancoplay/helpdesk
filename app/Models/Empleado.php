<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empleado extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('empleado_role', function (Builder $query): void {
            $query->whereHas('roles', function (Builder $roleQuery): void {
                $roleQuery->where('name', 'Empleado');
            });
        });
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

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'empleado_id');
    }
}

