<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaTrabajo extends Model
{
    use HasFactory;

    protected $table = 'areas_trabajo';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'area_trabajo_id');
    }
}

