<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        if (!Schema::hasColumn('clientes', 'user_id')) {
            Schema::table('clientes', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        DB::table('clientes')
            ->whereNull('user_id')
            ->orderBy('id')
            ->chunkById(200, function ($clientes): void {
                foreach ($clientes as $cliente) {
                    $email = mb_strtolower(trim((string) ($cliente->email ?? '')));
                    if ($email === '') {
                        continue;
                    }

                    $userId = DB::table('users')->where('email', $email)->value('id');
                    if (!$userId) {
                        continue;
                    }

                    DB::table('clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'user_id' => $userId,
                            'updated_at' => now(),
                        ]);
                }
            });

        if (!Schema::hasColumn('clientes', 'user_id')) {
            return;
        }

        try {
            Schema::table('clientes', function (Blueprint $table): void {
                $table->unique('user_id', 'clientes_user_id_unique');
            });
        } catch (\Throwable $e) {
            // In case duplicates exist in old data, keep nullable FK and skip unique.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('clientes') || !Schema::hasColumn('clientes', 'user_id')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table): void {
            try {
                $table->dropUnique('clientes_user_id_unique');
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $table->dropConstrainedForeignId('user_id');
            } catch (\Throwable $e) {
                $table->dropColumn('user_id');
            }
        });
    }
};

