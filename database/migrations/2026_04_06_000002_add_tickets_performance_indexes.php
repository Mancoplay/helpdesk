<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['estado', 'deleted_at'], 'tickets_estado_deleted_at_idx');
            $table->index(['departamento_id', 'estado'], 'tickets_departamento_estado_idx');
            $table->index('created_at', 'tickets_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_estado_deleted_at_idx');
            $table->dropIndex('tickets_departamento_estado_idx');
            $table->dropIndex('tickets_created_at_idx');
        });
    }
};

