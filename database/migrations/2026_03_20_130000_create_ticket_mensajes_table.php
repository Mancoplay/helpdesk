<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('mensaje');
            $table->string('imagen_path')->nullable();
            $table->string('imagen_nombre')->nullable();
            $table->string('imagen_mime')->nullable();
            $table->unsignedInteger('imagen_size')->nullable();
            $table->enum('tipo', ['creacion', 'atencion', 'comentario'])->default('comentario');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_mensajes');
    }
};
