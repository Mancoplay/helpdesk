<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ticket_eventos') || Schema::hasColumn('ticket_eventos', 'rustdesk_code')) {
            return;
        }

        Schema::table('ticket_eventos', function (Blueprint $table): void {
            $table->string('rustdesk_code', 80)->nullable()->after('support_code');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ticket_eventos') || !Schema::hasColumn('ticket_eventos', 'rustdesk_code')) {
            return;
        }

        Schema::table('ticket_eventos', function (Blueprint $table): void {
            $table->dropColumn('rustdesk_code');
        });
    }
};
