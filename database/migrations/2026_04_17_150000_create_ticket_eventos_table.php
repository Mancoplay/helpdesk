<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_eventos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('event_type', 20); // mensaje | remote

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('mensaje')->nullable();
            $table->string('imagen_path')->nullable();
            $table->string('imagen_nombre')->nullable();
            $table->string('imagen_mime')->nullable();
            $table->unsignedInteger('imagen_size')->nullable();
            $table->string('tipo', 20)->nullable(); // creacion | atencion | comentario

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->nullable();
            $table->string('support_code', 20)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['ticket_id', 'event_type']);
            $table->index(['ticket_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_eventos');
    }
};

