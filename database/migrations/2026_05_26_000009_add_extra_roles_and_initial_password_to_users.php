<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend role ENUM — requires raw SQL, no doctrine/dbal needed
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','trainer','schwimmer','elternteil','kampfrichter','vorstand') NOT NULL");

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'additional_roles')) {
                $table->json('additional_roles')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'initial_password')) {
                $table->string('initial_password')->nullable()->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(
                ['additional_roles', 'initial_password'],
                fn($c) => Schema::hasColumn('users', $c)
            );
            if ($cols) $table->dropColumn(array_values($cols));
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','trainer','schwimmer','elternteil') NOT NULL");
    }
};
