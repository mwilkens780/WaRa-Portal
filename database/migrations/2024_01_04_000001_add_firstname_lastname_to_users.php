<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'firstname')) {
                $table->string('firstname', 100)->default('')->after('name');
            }
            if (!Schema::hasColumn('users', 'lastname')) {
                $table->string('lastname', 100)->default('')->after('firstname');
            }
        });

        // Migrate existing name → firstname / lastname (split on first space)
        DB::table('users')->orderBy('id')->each(function ($user) {
            if ($user->firstname === '' && $user->lastname === '') {
                $parts = explode(' ', $user->name, 2);
                DB::table('users')->where('id', $user->id)->update([
                    'firstname' => trim($parts[0] ?? ''),
                    'lastname'  => trim($parts[1] ?? ''),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firstname', 'lastname']);
        });
    }
};
