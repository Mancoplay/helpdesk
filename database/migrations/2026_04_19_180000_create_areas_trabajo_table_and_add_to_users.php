<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        $now = now();
        $defaultAreas = [
            'General',
            'Area Legal',
            'Contabilidad',
            'Reclamos',
            'RRHH',
            'Soporte Tecnico',
            'Sistemas',
            'Redes',
            'Atencion al Cliente',
        ];

        foreach ($defaultAreas as $area) {
            DB::table('areas_trabajo')->updateOrInsert(
                ['nombre' => $area],
                ['descripcion' => 'Area de trabajo', 'activo' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $boliviaDepartments = [
            'La Paz',
            'Cochabamba',
            'Santa Cruz',
            'Oruro',
            'Potosi',
            'Chuquisaca',
            'Tarija',
            'Beni',
            'Pando',
        ];

        foreach ($boliviaDepartments as $name) {
            DB::table('departamentos')->updateOrInsert(
                ['nombre' => $name],
                ['descripcion' => 'Departamento de Bolivia', 'activo' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $defaultDepartmentId = (int) DB::table('departamentos')->where('nombre', 'La Paz')->value('id');
        $generalAreaId = (int) DB::table('areas_trabajo')->where('nombre', 'General')->value('id');
        $departmentsById = DB::table('departamentos')->pluck('nombre', 'id')->toArray();
        $geoSet = array_flip($boliviaDepartments);

        DB::table('users')->orderBy('id')->chunkById(200, function ($users) use ($departmentsById, $geoSet, $defaultDepartmentId, $generalAreaId, $now): void {
            foreach ($users as $user) {
                $departmentId = (int) ($user->departamento_id ?? 0);
                $departmentName = $departmentsById[$departmentId] ?? null;

                $finalDepartmentId = $departmentId > 0 ? $departmentId : $defaultDepartmentId;
                $finalAreaId = $generalAreaId;

                if (is_string($departmentName) && $departmentName !== '' && !isset($geoSet[$departmentName])) {
                    $areaId = (int) DB::table('areas_trabajo')->where('nombre', $departmentName)->value('id');
                    if ($areaId <= 0) {
                        DB::table('areas_trabajo')->insert([
                            'nombre' => $departmentName,
                            'descripcion' => 'Area migrada desde departamento',
                            'activo' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $areaId = (int) DB::table('areas_trabajo')->where('nombre', $departmentName)->value('id');
                    }
                    $finalAreaId = $areaId > 0 ? $areaId : $generalAreaId;
                    $finalDepartmentId = $defaultDepartmentId;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'departamento_id' => $finalDepartmentId > 0 ? $finalDepartmentId : null,
                    'area_trabajo_id' => $finalAreaId > 0 ? $finalAreaId : null,
                    'updated_at' => $now,
                ]);
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

