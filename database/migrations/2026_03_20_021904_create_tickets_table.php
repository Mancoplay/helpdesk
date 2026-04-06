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
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();
            $table->string('asunto', 180);
            $table->text('descripcion');
            $table->enum('estado', ['pendiente', 'en_proceso', 'finalizado', 'cerrado'])->default('pendiente');
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
