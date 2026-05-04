<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->unsignedTinyInteger('atencion_puntuacion')->nullable()->after('fecha_cierre');
            $table->foreignId('puntuado_por_id')->nullable()->after('atencion_puntuacion')->constrained('users')->nullOnDelete();
            $table->timestamp('puntuado_at')->nullable()->after('puntuado_por_id');

            $table->index(['empleado_id', 'atencion_puntuacion'], 'tickets_empleado_puntuacion_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('puntuacion_promedio', 3, 2)->default(0)->after('area_trabajo_id');
            $table->unsignedInteger('puntuaciones_count')->default(0)->after('puntuacion_promedio');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_empleado_puntuacion_idx');
            $table->dropConstrainedForeignId('puntuado_por_id');
            $table->dropColumn(['atencion_puntuacion', 'puntuado_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['puntuacion_promedio', 'puntuaciones_count']);
        });
    }
};
