<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_empleado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['empleado_id', 'departamento_id']);
        });

        $empleados = DB::table('empleados')
            ->select('id', 'departamento_id')
            ->whereNotNull('departamento_id')
            ->get();

        if ($empleados->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $empleados->map(fn ($empleado) => [
            'empleado_id' => $empleado->id,
            'departamento_id' => $empleado->departamento_id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('departamento_empleado')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_empleado');
    }
};
