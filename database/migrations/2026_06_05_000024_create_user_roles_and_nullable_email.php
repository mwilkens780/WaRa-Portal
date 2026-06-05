<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. user_roles pivot table ─────────────────────────────────────────
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->unique(['user_id', 'role']);
            $table->timestamps();
        });

        // ── 2. Migrate existing role + additional_roles → user_roles ──────────
        $now   = now();
        $users = DB::table('users')->get(['id', 'role', 'additional_roles']);
        foreach ($users as $user) {
            $roles = [];
            if ($user->role) {
                $roles[] = $user->role;
            }
            if ($user->additional_roles) {
                foreach (json_decode($user->additional_roles, true) ?? [] as $r) {
                    if ($r && !in_array($r, $roles, true)) {
                        $roles[] = $r;
                    }
                }
            }
            foreach ($roles as $role) {
                DB::table('user_roles')->insertOrIgnore([
                    'user_id'    => $user->id,
                    'role'       => $role,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // ── 3. Drop additional_roles column ───────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'additional_roles')) {
                $table->dropColumn('additional_roles');
            }
        });

        // ── 4. Make email nullable (preserves unique index) ───────────────────
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Re-add additional_roles and restore NOT NULL on email
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'additional_roles')) {
                $table->json('additional_roles')->nullable()->after('role');
            }
        });
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
        Schema::dropIfExists('user_roles');
    }
};
