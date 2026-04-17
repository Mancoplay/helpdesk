<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('cliente_role', function (Builder $query): void {
            $query->whereHas('roles', function (Builder $roleQuery): void {
                $roleQuery->whereIn('name', ['Usuario', 'Cliente']);
            });
        });
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'cliente_id');
    }
}

