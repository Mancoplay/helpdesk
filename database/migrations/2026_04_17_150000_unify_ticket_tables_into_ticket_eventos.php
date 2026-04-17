<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ticket_eventos')) {
            Schema::create('ticket_eventos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->string('event_type', 20); // mensaje | remote

                // Campos de mensaje
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('mensaje')->nullable();
                $table->string('imagen_path')->nullable();
                $table->string('imagen_nombre')->nullable();
                $table->string('imagen_mime')->nullable();
                $table->unsignedInteger('imagen_size')->nullable();
                $table->string('tipo', 20)->nullable(); // creacion | atencion | comentario

                // Campos de soporte remoto
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

        if (Schema::hasTable('ticket_mensajes')) {
            DB::table('ticket_mensajes')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    $inserts = [];
                    foreach ($rows as $row) {
                        $inserts[] = [
                            'ticket_id' => $row->ticket_id,
                            'event_type' => 'mensaje',
                            'user_id' => $row->user_id,
                            'mensaje' => $row->mensaje,
                            'imagen_path' => $row->imagen_path,
                            'imagen_nombre' => $row->imagen_nombre,
                            'imagen_mime' => $row->imagen_mime,
                            'imagen_size' => $row->imagen_size,
                            'tipo' => $row->tipo,
                            'requested_by_user_id' => null,
                            'cancelled_by_user_id' => null,
                            'status' => null,
                            'support_code' => null,
                            'requested_at' => null,
                            'responded_at' => null,
                            'ended_at' => null,
                            'note' => null,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                    }

                    if (!empty($inserts)) {
                        DB::table('ticket_eventos')->insert($inserts);
                    }
                });
        }

        if (Schema::hasTable('ticket_remote_sessions')) {
            DB::table('ticket_remote_sessions')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    $inserts = [];
                    foreach ($rows as $row) {
                        $inserts[] = [
                            'ticket_id' => $row->ticket_id,
                            'event_type' => 'remote',
                            'user_id' => null,
                            'mensaje' => null,
                            'imagen_path' => null,
                            'imagen_nombre' => null,
                            'imagen_mime' => null,
                            'imagen_size' => null,
                            'tipo' => null,
                            'requested_by_user_id' => $row->requested_by_user_id,
                            'cancelled_by_user_id' => $row->cancelled_by_user_id,
                            'status' => $row->status,
                            'support_code' => $row->support_code,
                            'requested_at' => $row->requested_at,
                            'responded_at' => $row->responded_at,
                            'ended_at' => $row->ended_at,
                            'note' => $row->note,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                    }

                    if (!empty($inserts)) {
                        DB::table('ticket_eventos')->insert($inserts);
                    }
                });
        }

        if (Schema::hasTable('ticket_mensajes')) {
            Schema::drop('ticket_mensajes');
        }

        if (Schema::hasTable('ticket_remote_sessions')) {
            Schema::drop('ticket_remote_sessions');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_eventos')) {
            Schema::drop('ticket_eventos');
        }
    }
};

