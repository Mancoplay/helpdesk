<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 25)->unique();
            $table->foreignId('cliente_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();
            $table->string('asunto', 180);
            $table->text('descripcion');
            $table->enum('estado', ['pendiente', 'en_proceso', 'finalizado', 'cerrado'])->default('pendiente');
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'created_at'], 'tickets_estado_created_idx');
            $table->index(['departamento_id', 'estado'], 'tickets_departamento_estado_idx');
            $table->index(['empleado_id', 'estado'], 'tickets_empleado_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
