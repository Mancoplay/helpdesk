<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'nombres')) {
                $table->string('nombres', 100)->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'apellidos')) {
                $table->string('apellidos', 100)->nullable()->after('nombres');
            }
            if (!Schema::hasColumn('users', 'telefono')) {
                $table->string('telefono', 30)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'direccion')) {
                $table->text('direccion')->nullable()->after('telefono');
            }
            if (!Schema::hasColumn('users', 'empresa')) {
                $table->string('empresa', 120)->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('users', 'cargo')) {
                $table->string('cargo', 100)->nullable()->after('empresa');
            }
            if (!Schema::hasColumn('users', 'activo')) {
                $table->boolean('activo')->default(true)->after('cargo');
            }
            if (!Schema::hasColumn('users', 'departamento_id')) {
                $table->foreignId('departamento_id')
                    ->nullable()
                    ->after('activo')
                    ->constrained('departamentos')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'departamento_id')) {
                try {
                    $table->dropForeign(['departamento_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('departamento_id');
            }

            foreach (['nombres', 'apellidos', 'telefono', 'direccion', 'empresa', 'cargo', 'activo'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
