<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clientes', 'segundo_nombre') || !Schema::hasColumn('clientes', 'direccion')) {
            Schema::table('clientes', function (Blueprint $table) {
                if (!Schema::hasColumn('clientes', 'segundo_nombre')) {
                    $table->string('segundo_nombre', 100)->nullable()->after('nombres');
                }
                if (!Schema::hasColumn('clientes', 'direccion')) {
                    $table->text('direccion')->nullable()->after('telefono');
                }
            });
        }

        if (!Schema::hasColumn('empleados', 'segundo_nombre') || !Schema::hasColumn('empleados', 'direccion')) {
            Schema::table('empleados', function (Blueprint $table) {
                if (!Schema::hasColumn('empleados', 'segundo_nombre')) {
                    $table->string('segundo_nombre', 100)->nullable()->after('nombres');
                }
                if (!Schema::hasColumn('empleados', 'direccion')) {
                    $table->text('direccion')->nullable()->after('telefono');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'segundo_nombre')) {
                $table->dropColumn('segundo_nombre');
            }
            if (Schema::hasColumn('clientes', 'direccion')) {
                $table->dropColumn('direccion');
            }
        });

        Schema::table('empleados', function (Blueprint $table) {
            if (Schema::hasColumn('empleados', 'segundo_nombre')) {
                $table->dropColumn('segundo_nombre');
            }
            if (Schema::hasColumn('empleados', 'direccion')) {
                $table->dropColumn('direccion');
            }
        });
    }
};
