<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'segundo_nombre')) {
            DB::table('clientes')
                ->select(['id', 'nombres', 'segundo_nombre'])
                ->whereNotNull('segundo_nombre')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $nombres = trim((string) ($row->nombres ?? ''));
                        $segundoNombre = trim((string) ($row->segundo_nombre ?? ''));
                        if ($segundoNombre === '') {
                            continue;
                        }

                        DB::table('clientes')
                            ->where('id', $row->id)
                            ->update([
                                'nombres' => trim($nombres . ' ' . $segundoNombre),
                                'updated_at' => now(),
                            ]);
                    }
                });

            Schema::table('clientes', function (Blueprint $table): void {
                $table->dropColumn('segundo_nombre');
            });
        }

        if (Schema::hasTable('empleados') && Schema::hasColumn('empleados', 'segundo_nombre')) {
            DB::table('empleados')
                ->select(['id', 'nombres', 'segundo_nombre'])
                ->whereNotNull('segundo_nombre')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $nombres = trim((string) ($row->nombres ?? ''));
                        $segundoNombre = trim((string) ($row->segundo_nombre ?? ''));
                        if ($segundoNombre === '') {
                            continue;
                        }

                        DB::table('empleados')
                            ->where('id', $row->id)
                            ->update([
                                'nombres' => trim($nombres . ' ' . $segundoNombre),
                                'updated_at' => now(),
                            ]);
                    }
                });

            Schema::table('empleados', function (Blueprint $table): void {
                $table->dropColumn('segundo_nombre');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clientes') && !Schema::hasColumn('clientes', 'segundo_nombre')) {
            Schema::table('clientes', function (Blueprint $table): void {
                $table->string('segundo_nombre', 100)->nullable()->after('nombres');
            });
        }

        if (Schema::hasTable('empleados') && !Schema::hasColumn('empleados', 'segundo_nombre')) {
            Schema::table('empleados', function (Blueprint $table): void {
                $table->string('segundo_nombre', 100)->nullable()->after('nombres');
            });
        }
    }
};

