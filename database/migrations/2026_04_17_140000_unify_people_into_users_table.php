<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureUserProfileColumns();
        $this->migrateEmpleadoDataToUsers();
        $this->migrateClienteDataToUsers();
        $this->migrateDepartamentoEmpleadoPivot();
        $this->migrateTicketPeopleForeignKeys();
        $this->dropLegacyPeopleTables();
    }

    public function down(): void
    {
        // This migration is intentionally one-way due to data consolidation.
    }

    private function ensureUserProfileColumns(): void
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
                $table->foreignId('departamento_id')->nullable()->after('activo')->constrained('departamentos')->nullOnDelete();
            }
        });

        DB::table('users')
            ->whereNull('nombres')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $rawName = trim((string) ($row->name ?? ''));
                    if ($rawName === '') {
                        continue;
                    }

                    $parts = preg_split('/\s+/', $rawName) ?: [];
                    $first = trim((string) ($parts[0] ?? ''));
                    $last = trim((string) implode(' ', array_slice($parts, 1)));

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update([
                            'nombres' => $first !== '' ? $first : $rawName,
                            'apellidos' => $last !== '' ? $last : null,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function migrateEmpleadoDataToUsers(): void
    {
        if (!Schema::hasTable('empleados')) {
            return;
        }

        DB::table('empleados')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $empleado) {
                    $email = mb_strtolower(trim((string) ($empleado->email ?? '')));
                    $userId = $empleado->user_id ?: null;

                    if (!$userId && $email !== '') {
                        $userId = DB::table('users')->where('email', $email)->value('id');
                    }
                    if (!$userId) {
                        continue;
                    }

                    DB::table('users')
                        ->where('id', $userId)
                        ->update([
                            'name' => trim(((string) ($empleado->nombres ?? '')) . ' ' . ((string) ($empleado->apellidos ?? ''))),
                            'nombres' => $empleado->nombres,
                            'apellidos' => $empleado->apellidos,
                            'email' => $email !== '' ? $email : DB::raw('email'),
                            'telefono' => $empleado->telefono,
                            'direccion' => $empleado->direccion,
                            'cargo' => $empleado->cargo,
                            'activo' => (bool) ($empleado->activo ?? true),
                            'departamento_id' => $empleado->departamento_id,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function migrateClienteDataToUsers(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        DB::table('clientes')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $cliente) {
                    $email = mb_strtolower(trim((string) ($cliente->email ?? '')));
                    $userId = null;

                    if (Schema::hasColumn('clientes', 'user_id')) {
                        $userId = $cliente->user_id ?: null;
                    }

                    if (!$userId && $email !== '') {
                        $userId = DB::table('users')->where('email', $email)->value('id');
                    }
                    if (!$userId) {
                        continue;
                    }

                    DB::table('users')
                        ->where('id', $userId)
                        ->update([
                            'name' => trim(((string) ($cliente->nombres ?? '')) . ' ' . ((string) ($cliente->apellidos ?? ''))),
                            'nombres' => $cliente->nombres,
                            'apellidos' => $cliente->apellidos,
                            'email' => $email !== '' ? $email : DB::raw('email'),
                            'telefono' => $cliente->telefono,
                            'direccion' => $cliente->direccion,
                            'empresa' => $cliente->empresa,
                            'activo' => (bool) ($cliente->activo ?? true),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function migrateDepartamentoEmpleadoPivot(): void
    {
        if (!Schema::hasTable('departamento_empleado')) {
            return;
        }

        Schema::table('departamento_empleado', function (Blueprint $table): void {
            if (!Schema::hasColumn('departamento_empleado', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
        });

        if (Schema::hasTable('empleados') && Schema::hasColumn('departamento_empleado', 'empleado_id')) {
            DB::table('departamento_empleado')
                ->whereNull('user_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $empleado = DB::table('empleados')
                            ->where('id', $row->empleado_id)
                            ->first(['user_id', 'email']);

                        if (!$empleado) {
                            continue;
                        }

                        $mappedUserId = $empleado->user_id;
                        if (!$mappedUserId && !empty($empleado->email)) {
                            $mappedUserId = DB::table('users')
                                ->where('email', mb_strtolower(trim((string) $empleado->email)))
                                ->value('id');
                        }

                        if (!$mappedUserId) {
                            continue;
                        }

                        DB::table('departamento_empleado')
                            ->where('id', $row->id)
                            ->update([
                                'user_id' => $mappedUserId,
                                'updated_at' => now(),
                            ]);
                    }
                });
        }

        $now = now();
        $employeeUsers = DB::table('users')
            ->whereNotNull('departamento_id')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', \App\Models\User::class)
                    ->where('roles.name', 'Empleado');
            })
            ->get(['id', 'departamento_id']);

        foreach ($employeeUsers as $user) {
            $exists = DB::table('departamento_empleado')
                ->where('user_id', $user->id)
                ->where('departamento_id', $user->departamento_id)
                ->exists();

            if (!$exists) {
                DB::table('departamento_empleado')->insert([
                    'user_id' => $user->id,
                    'departamento_id' => $user->departamento_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('departamento_empleado', function (Blueprint $table): void {
            try {
                $table->dropUnique(['empleado_id', 'departamento_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $table->dropForeign(['empleado_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $table->dropColumn('empleado_id');
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('departamento_empleado', function (Blueprint $table): void {
            try {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $table->unique(['user_id', 'departamento_id']);
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    private function migrateTicketPeopleForeignKeys(): void
    {
        if (!Schema::hasTable('tickets')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            try {
                $table->dropForeign(['cliente_id']);
            } catch (\Throwable $e) {
                // ignore
            }
            try {
                $table->dropForeign(['empleado_id']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        DB::table('tickets')
            ->orderBy('id')
            ->chunkById(200, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    $newClienteId = $ticket->cliente_id;
                    $newEmpleadoId = $ticket->empleado_id;

                    if (Schema::hasTable('clientes') && !empty($ticket->cliente_id)) {
                        $cliente = DB::table('clientes')
                            ->where('id', $ticket->cliente_id)
                            ->first();

                        if ($cliente) {
                            $newClienteId = Schema::hasColumn('clientes', 'user_id') ? ($cliente->user_id ?? null) : null;
                            if (!$newClienteId && !empty($cliente->email)) {
                                $newClienteId = DB::table('users')
                                    ->where('email', mb_strtolower(trim((string) $cliente->email)))
                                    ->value('id');
                            }
                        }
                    }

                    if (Schema::hasTable('empleados') && !empty($ticket->empleado_id)) {
                        $empleado = DB::table('empleados')
                            ->where('id', $ticket->empleado_id)
                            ->first();

                        if ($empleado) {
                            $newEmpleadoId = $empleado->user_id ?? null;
                            if (!$newEmpleadoId && !empty($empleado->email)) {
                                $newEmpleadoId = DB::table('users')
                                    ->where('email', mb_strtolower(trim((string) $empleado->email)))
                                    ->value('id');
                            }
                        }
                    }

                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update([
                            'cliente_id' => $newClienteId,
                            'empleado_id' => $newEmpleadoId,
                            'updated_at' => now(),
                        ]);
                }
            });

        Schema::table('tickets', function (Blueprint $table): void {
            try {
                $table->foreign('cliente_id')->references('id')->on('users')->restrictOnDelete();
            } catch (\Throwable $e) {
                // ignore
            }
            try {
                $table->foreign('empleado_id')->references('id')->on('users')->nullOnDelete();
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    private function dropLegacyPeopleTables(): void
    {
        if (Schema::hasTable('clientes')) {
            Schema::drop('clientes');
        }

        if (Schema::hasTable('empleados')) {
            Schema::drop('empleados');
        }
    }
};
