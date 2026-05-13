<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->json('assigned_employee_ids')->nullable()->after('empleado_id');
            $table->string('assignment_request_type', 40)->nullable()->after('last_notified_at');
            $table->foreignId('assignment_request_by_id')->nullable()->after('assignment_request_type')->constrained('users')->nullOnDelete();
            $table->timestamp('assignment_request_at')->nullable()->after('assignment_request_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assignment_request_by_id');
            $table->dropColumn([
                'assigned_employee_ids',
                'assignment_request_type',
                'assignment_request_at',
            ]);
        });
    }
};
