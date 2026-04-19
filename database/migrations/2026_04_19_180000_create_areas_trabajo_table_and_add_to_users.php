<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas_trabajo', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'area_trabajo_id')) {
                $table->foreignId('area_trabajo_id')
                    ->nullable()
                    ->after('departamento_id')
                    ->constrained('areas_trabajo')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'area_trabajo_id')) {
                try {
                    $table->dropForeign(['area_trabajo_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('area_trabajo_id');
            }
        });

        Schema::dropIfExists('areas_trabajo');
    }
};
