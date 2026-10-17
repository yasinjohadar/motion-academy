<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Every account becomes an admin or a student:
     *  - accounts with no role at all (older sign-ups, imported users) → student;
     *  - the `teacher` and `admin-staff` roles are retired; their holders → student.
     *
     * Written against the tables directly so it does not depend on model or
     * config state at the time it runs.
     */
    public function up(): void
    {
        $now = now();

        $studentId = DB::table('roles')->where('name', 'student')->where('guard_name', 'web')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'student', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        $retired = DB::table('roles')->whereIn('name', ['teacher', 'admin-staff'])->pluck('id');

        DB::transaction(function () use ($studentId, $retired): void {
            if ($retired->isNotEmpty()) {
                DB::table('model_has_roles')->whereIn('role_id', $retired)->delete();
                DB::table('role_has_permissions')->whereIn('role_id', $retired)->delete();
                DB::table('roles')->whereIn('id', $retired)->delete();
            }

            $withRole = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id');

            DB::table('users')
                ->whereNotIn('id', $withRole)
                ->orderBy('id')
                ->pluck('id')
                ->chunk(500)
                ->each(fn ($ids) => DB::table('model_has_roles')->insert(
                    $ids->map(fn ($id) => ['role_id' => $studentId, 'model_type' => 'App\\Models\\User', 'model_id' => $id])->all()
                ));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Role assignments made here are indistinguishable from real ones afterwards,
     * so there is nothing safe to undo.
     */
    public function down(): void {}
};
