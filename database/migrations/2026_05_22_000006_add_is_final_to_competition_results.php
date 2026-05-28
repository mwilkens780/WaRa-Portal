<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('competition_results', 'is_final')) {
            Schema::table('competition_results', function (Blueprint $table) {
                $table->boolean('is_final')->default(false)->after('breaks_landesrekord');
            });
        }
    }

    public function down(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->dropColumn('is_final');
        });
    }
};
