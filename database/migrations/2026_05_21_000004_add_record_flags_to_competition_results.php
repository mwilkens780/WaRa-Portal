<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            if (!Schema::hasColumn('competition_results', 'gender')) {
                $table->char('gender', 1)->nullable()->after('age_group');
            }
            if (!Schema::hasColumn('competition_results', 'breaks_vereinsrekord')) {
                $table->boolean('breaks_vereinsrekord')->default(false)->after('gender');
            }
            if (!Schema::hasColumn('competition_results', 'breaks_landesrekord')) {
                $table->boolean('breaks_landesrekord')->default(false)->after('breaks_vereinsrekord');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->dropColumn(['gender', 'breaks_vereinsrekord', 'breaks_landesrekord']);
        });
    }
};
