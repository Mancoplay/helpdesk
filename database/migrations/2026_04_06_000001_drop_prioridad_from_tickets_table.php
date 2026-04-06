<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'prioridad')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('prioridad');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tickets', 'prioridad')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->enum('prioridad', ['baja', 'media', 'alta'])->default('media')->after('estado');
            });
        }
    }
};

